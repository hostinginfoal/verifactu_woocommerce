<?php
/**
 * VeriFactu Service — orchestrates invoice submission to InFoAL VeriFactu API.
 *
 * @package VeriFactu_InFoAL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Verifactu_Service_Verifactu {

    /** @var Verifactu_Api_Client */
    private $api;

    /** @var bool */
    private $debug;

    public function __construct() {
        $this->debug = Verifactu_Infoal::is_debug();
        $this->api   = new Verifactu_Api_Client(
            Verifactu_Infoal::get_option( 'api_token', '' ),
            $this->debug
        );
    }

    // ── Alta (new invoice) ────────────────────────────────────────────────────

    public function send_alta( $order_id, $tipo = 'alta' ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return array( 'success' => false, 'error' => 'Pedido no encontrado.' );
        }

        // 1. Build Payload
        $payload = $this->build_alta_payload( $order, $tipo );
        if ( is_wp_error( $payload ) ) {
            return array( 'success' => false, 'error' => $payload->get_error_message() );
        }

        // 2. Check if it's already sent and not in an error state
        $existing_status = $this->get_status_for_order( $order_id, $tipo === 'alta' ? 'invoice' : 'refund' );
        if ( $existing_status && in_array( $existing_status['estado'], array( 'pendiente', 'sincronizado', 'Correcto' ), true ) ) {
             return array( 'success' => false, 'error' => 'Ya existe un registro enviado para este pedido.' );
        }

        // 3. Send via API Client
        $result = $this->api->send_alta( $payload );

        // 4. Handle response and persist
        return $this->handle_alta_response( $result, $order_id, $tipo, $payload );
    }

    // ── Anulación (cancellation) ──────────────────────────────────────────────

    public function send_anulacion( $order_id, $tipo = 'alta' ) {
        $existing_status = $this->get_status_for_order( $order_id, $tipo === 'alta' ? 'invoice' : 'refund' );
        
        if ( ! $existing_status || empty( $existing_status['invoice_number'] ) ) {
            return array( 'success' => false, 'error' => 'No se encontró la factura para anular.' );
        }

        $payload = array(
            'InvoiceNumber' => $existing_status['invoice_number']
        );

        $result = $this->api->send_anulacion( $payload );

        if ( isset( $result['response'] ) && $result['response'] === 'OK' ) {
            $table = $tipo === 'alta' ? 'verifactu_order_invoice' : 'verifactu_order_refund';
            $id_field = $tipo === 'alta' ? 'id_order_invoice' : 'id_order_refund';

            global $wpdb;
            $table_name = $wpdb->prefix . $table;
            $wpdb->update(
                $table_name,
                array(
                    'estado'      => 'pendiente',
                    'anulacion'   => 1,
                    'id_reg_fact' => isset( $result['id_reg_fact'] ) ? (int)$result['id_reg_fact'] : 0,
                ),
                array( $id_field => $order_id )
            );

            return array( 'success' => true );
        }

        return array( 'success' => false, 'error' => isset( $result['error'] ) ? $result['error'] : 'Error desconocido al anular.' );
    }

    // ── Credit note (abono / refund) ──────────────────────────────────────────

    public function send_abono( $order_id, $refund_id ) {
        return $this->send_alta( $refund_id, 'abono' ); // Pass refund_id, WooCommerce treats refunds as order type
    }

    // ── NIF validation ────────────────────────────────────────────────────────

    public function check_nif( $nif ) {
        $result = $this->api->check_nif( $nif );
        if ( isset( $result['valid'] ) && $result['valid'] ) {
            return array( 'success' => true, 'valid' => true, 'message' => 'NIF/DNI válido' );
        }
        return array( 'success' => false, 'valid' => false, 'error' => isset( $result['error'] ) ? $result['error'] : 'NIF/DNI no válido' );
    }

    // ── Cron / background sync ────────────────────────────────────────────────

    public function run_cron_sync() {
        Verifactu_Infoal::log( 'Iniciando cron sync', 1 );
        
        global $wpdb;

        // 1. Check Pendientes (Invoices)
        $invoices = $wpdb->get_results( "SELECT id_reg_fact FROM {$wpdb->prefix}verifactu_order_invoice WHERE estado = 'pendiente' AND id_reg_fact > 0" );
        $ids = array();
        foreach ( $invoices as $inv ) {
            $ids[] = (int) $inv->id_reg_fact;
        }

        // 2. Check Pendientes (Refunds)
        $refunds = $wpdb->get_results( "SELECT id_reg_fact FROM {$wpdb->prefix}verifactu_order_refund WHERE estado = 'pendiente' AND id_reg_fact > 0" );
        foreach ( $refunds as $ref ) {
            $ids[] = (int) $ref->id_reg_fact;
        }

        if ( ! empty( $ids ) ) {
            $results = $this->api->check_pending( $ids );
            
            if ( is_array( $results ) ) {
                foreach ( $results as $res ) {
                    if ( ! isset( $res['id_reg_fact'] ) || in_array( $res['estado_queue'], array( 'pendiente', 'procesando' ), true ) ) {
                        continue;
                    }

                    $id_reg_fact = (int) $res['id_reg_fact'];
                    $update_data = array(
                        'estado'                            => 'sincronizado',
                        'verifactuEstadoRegistro'           => isset( $res['EstadoRegistro'] ) ? $res['EstadoRegistro'] : '',
                        'verifactuEstadoEnvio'              => isset( $res['EstadoEnvio'] ) ? $res['EstadoEnvio'] : '',
                        'verifactuCodigoErrorRegistro'      => isset( $res['CodigoErrorRegistro'] ) ? $res['CodigoErrorRegistro'] : '',
                        'verifactuDescripcionErrorRegistro' => isset( $res['DescripcionErrorRegistro'] ) ? $res['DescripcionErrorRegistro'] : '',
                        'urlQR'                             => isset( $res['urlQR'] ) ? $res['urlQR'] : '',
                    );

                    // Update Invoice
                    $wpdb->update( "{$wpdb->prefix}verifactu_order_invoice", $update_data, array( 'id_reg_fact' => $id_reg_fact ) );
                    
                    // Update Refund
                    $wpdb->update( "{$wpdb->prefix}verifactu_order_refund", $update_data, array( 'id_reg_fact' => $id_reg_fact ) );
                }
            }
        }

        $this->retry_failed();
    }

    public function retry_failed() {
        global $wpdb;
        $max_retries = 5;

        // Invoices
        $this->retry_failed_records( $wpdb->prefix . 'verifactu_order_invoice', 'id_order_invoice', $max_retries, 'alta' );

        // Refunds
        $this->retry_failed_records( $wpdb->prefix . 'verifactu_order_refund', 'id_refund', $max_retries, 'abono' );
    }

    private function retry_failed_records( $table_name, $id_field, $max_retries, $tipo ) {
        global $wpdb;

        $failed_records = $wpdb->get_results( "SELECT $id_field as object_id, anulacion, retry_count FROM $table_name WHERE estado = 'api_error' AND retry_count < $max_retries" );

        foreach ( $failed_records as $record ) {
            $object_id = (int) $record->object_id;
            $anulacion = (int) $record->anulacion;
            $retry_count = (int) $record->retry_count;

            $wpdb->update(
                $table_name,
                array( 'retry_count' => $retry_count + 1 ),
                array( $id_field => $object_id )
            );

            if ( $anulacion === 1 ) {
                $this->send_anulacion( $object_id, $tipo );
            } elseif ( $tipo === 'abono' ) {
                // If it's a refund, object_id is the refund id
                $refund = wc_get_order( $object_id );
                if ( $refund ) {
                    $this->send_abono( $refund->get_parent_id(), $object_id );
                }
            } else {
                $this->send_alta( $object_id, 'alta' );
            }
        }

        // Mark as failed permanently
        $wpdb->query( "UPDATE $table_name SET estado = 'failed' WHERE estado = 'api_error' AND retry_count >= $max_retries" );
    }

    // ── Payload builders ──────────────────────────────────────────────────────

    public function build_alta_payload( WC_Order $order, $tipo = 'alta' ) {
        $payload = array();

        $nif_emisor = Verifactu_Infoal::get_option( 'nif_emisor', '' );
        if ( empty( $nif_emisor ) ) {
            return new WP_Error( 'no_nif_emisor', 'NIF Emisor no configurado.' );
        }

        $payload['buyer'] = $this->build_buyer( $order );
        
        $invoice = array();
        $invoice['InvoiceCurrencyCode'] = $order->get_currency();
        $invoice['TaxCurrencyCode']     = $order->get_currency();
        $invoice['LanguageName']        = 'es';

        $total_tax_excl = (float) $order->get_total() - (float) $order->get_total_tax();
        $total_tax_incl = (float) $order->get_total();
        
        if ( $tipo === 'abono' ) {
            $invoice_number = $this->get_formatted_refund_number( $order );
            $invoice['InvoiceNumber'] = $invoice_number;
            $invoice['InvoiceDocumentType'] = empty( $payload['buyer']['TaxIdentificationNumber'] ) ? 'FA' : 'FC';
            $invoice['InvoiceClass'] = 'OR'; // Factura rectificativa
            $invoice['IssueDate'] = $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d' ) : date( 'Y-m-d' );
            
            $invoice['TotalGrossAmount'] = -$total_tax_excl;
            $invoice['TotalGrossAmountBeforeTaxes'] = -$total_tax_excl;
            $invoice['InvoiceTotal'] = -$total_tax_incl;
            $invoice['TotalOutstandingAmount'] = -$total_tax_incl;
            $invoice['TotalExecutableAmount'] = -$total_tax_incl;
            $invoice['TotalTaxOutputs'] = -((float) $order->get_total_tax());

            $parent_order = wc_get_order( $order->get_parent_id() );
            $invoice['CorrectiveCorrectionMethod'] = "01";
            $invoice['CorrectiveCorrectionMethodDescription'] = "Abono de factura";
            $invoice['CorrectiveInvoiceNumber'] = $parent_order ? $this->get_formatted_invoice_number( $parent_order ) : '';
            if ( $parent_order && $parent_order->get_date_created() ) {
                $invoice['CorrectiveIssueDate'] = $parent_order->get_date_created()->date( 'Y-m-d' );
            }

        } else {
            $invoice_number = $this->get_formatted_invoice_number( $order );
            $invoice['InvoiceNumber'] = $invoice_number;
            $invoice['InvoiceDocumentType'] = empty( $payload['buyer']['TaxIdentificationNumber'] ) ? 'FA' : 'FC'; // FA = Simplificada, FC = Completa
            $invoice['InvoiceClass'] = 'OO'; // Factura Ordinaria
            $invoice['IssueDate'] = $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d' ) : date( 'Y-m-d' );

            $invoice['TotalGrossAmount'] = $total_tax_excl;
            $invoice['TotalGrossAmountBeforeTaxes'] = $total_tax_excl;
            $invoice['InvoiceTotal'] = $total_tax_incl;
            $invoice['TotalOutstandingAmount'] = $total_tax_incl;
            $invoice['TotalExecutableAmount'] = $total_tax_incl;
            $invoice['TotalTaxOutputs'] = (float) $order->get_total_tax();
        }

        $invoice['lines'] = $this->build_lines( $order, $tipo );

        // Fiscal flags
        if ( $this->is_order_oss( $order ) || $this->is_export_invoice( $order ) ) {
            $invoice['TotalTaxOutputs'] = 0;
            $invoice['InvoiceTotal'] = $tipo === 'abono' ? -$total_tax_excl : $total_tax_excl;
        }

        $payload['invoice'] = $invoice;
        
        // Pass invoice number to be used later
        $payload['_invoice_number'] = $invoice_number;

        return $payload;
    }

    public function build_buyer( WC_Order $order ) {
        $buyer = array();
        
        $vat_number = $order->get_meta( '_billing_vat_number' ) ?: $order->get_meta( '_billing_nif' );
        
        $buyer['TaxIdentificationNumber'] = $vat_number;
        $buyer['CorporateName']           = $order->get_billing_company();
        $buyer['Name']                    = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        $buyer['Address']                 = $order->get_billing_address_1();
        $buyer['PostCode']                = $order->get_billing_postcode();
        $buyer['Town']                    = $order->get_billing_city();
        $buyer['Province']                = $order->get_billing_state();
        $buyer['CountryCode']             = $order->get_billing_country();

        return $buyer;
    }

    public function build_lines( WC_Order $order, $tipo = 'alta' ) {
        $lines = array();
        $seq = 1;
        $sign = $tipo === 'abono' ? -1 : 1;

        $is_oss    = $this->is_order_oss( $order );
        $is_export = $this->is_export_invoice( $order );
        $is_b2b    = $this->is_b2b_intracommunity( $order );

        foreach ( $order->get_items() as $item_id => $item ) {
            $line = array();
            $line['SequenceNumber']      = $seq++;
            $line['ItemDescription']     = $item->get_name();
            $line['Quantity']            = $item->get_quantity();
            $line['UnitPriceWithoutTax'] = $sign * ( (float) $item->get_subtotal() / max( 1, $item->get_quantity() ) );
            $line['TotalCost']           = $sign * (float) $item->get_total() + (float) $item->get_total_tax();
            $line['GrossAmount']         = $sign * (float) $item->get_total() + (float) $item->get_total_tax();
            $line['ArticleCode']         = $item->get_product() ? $item->get_product()->get_sku() : '';

            $tax_rate = 0;
            $taxes = $item->get_taxes();
            if ( ! empty( $taxes['subtotal'] ) ) {
                foreach ( $taxes['subtotal'] as $tax_id => $tax_amount ) {
                    $tax_rate = WC_Tax::get_rate_percent_value( $tax_id );
                    break; // Just use the first tax rate for now
                }
            }

            $line['TaxRate']           = $tax_rate;
            $line['TaxableBaseAmount'] = $sign * (float) $item->get_total();
            $line['TaxAmountTotal']    = $sign * (float) $item->get_total_tax();
            $line['TaxTypeCode']       = '01'; // Default IVA

            // Territory specific tax
            if ( Verifactu_Infoal::get_option( 'territorio_especial', 0 ) ) {
                 // Simplification: In a real environment, we should check if the applied tax is IGIC (03) or IPSI (02)
                 $line['TaxTypeCode'] = '03'; 
            }

            // Cross-border logic
            if ( $is_oss ) {
                $line['OperationQualification'] = "N2";
                $line['RegimeKey'] = "17";
                $line['TaxRate'] = 0;
                $line['TaxAmountTotal'] = 0;
            } elseif ( $is_export ) {
                $line['RegimeKey'] = "02";
                $line['ExemptOperation'] = "E2";
                $line['TaxRate'] = 0;
                $line['TaxAmountTotal'] = 0;
            } elseif ( $is_b2b ) {
                $line['OperationQualification'] = $tax_rate == 0 ? "S2" : "S1";
                $line['RegimeKey'] = "01";
            } else {
                $line['OperationQualification'] = "S1";
                $line['RegimeKey'] = "01";
            }

            $lines[] = $line;
        }

        // Add Shipping Line
        if ( $order->get_shipping_total() > 0 ) {
            $shipping_line = array();
            $shipping_line['SequenceNumber']      = $seq++;
            $shipping_line['ItemDescription']     = 'Gastos de Envío';
            $shipping_line['Quantity']            = 1;
            $shipping_line['UnitPriceWithoutTax'] = $sign * (float) $order->get_shipping_total();
            $shipping_line['TotalCost']           = $sign * ( (float) $order->get_shipping_total() + (float) $order->get_shipping_tax() );
            $shipping_line['GrossAmount']         = $sign * ( (float) $order->get_shipping_total() + (float) $order->get_shipping_tax() );
            $shipping_line['ArticleCode']         = 'ENVIO';
            $shipping_line['TaxTypeCode']         = '01';

            $shipping_tax_rate = 0;
            if ( (float) $order->get_shipping_tax() > 0 && (float) $order->get_shipping_total() > 0 ) {
                $shipping_tax_rate = round( ( (float) $order->get_shipping_tax() / (float) $order->get_shipping_total() ) * 100, 1 );
            }

            $shipping_line['TaxRate']           = $shipping_tax_rate;
            $shipping_line['TaxableBaseAmount'] = $sign * (float) $order->get_shipping_total();
            $shipping_line['TaxAmountTotal']    = $sign * (float) $order->get_shipping_tax();
            
            // Cross-border logic
            if ( $is_oss ) {
                $shipping_line['OperationQualification'] = "N2";
                $shipping_line['RegimeKey'] = "17";
                $shipping_line['TaxRate'] = 0;
                $shipping_line['TaxAmountTotal'] = 0;
            } elseif ( $is_export ) {
                $shipping_line['RegimeKey'] = "02";
                $shipping_line['ExemptOperation'] = "E2";
                $shipping_line['TaxRate'] = 0;
                $shipping_line['TaxAmountTotal'] = 0;
            } elseif ( $is_b2b ) {
                $shipping_line['OperationQualification'] = $shipping_tax_rate == 0 ? "S2" : "S1";
                $shipping_line['RegimeKey'] = "01";
            } else {
                $shipping_line['OperationQualification'] = "S1";
                $shipping_line['RegimeKey'] = "01";
            }

            $lines[] = $shipping_line;
        }

        return $lines;
    }

    // ── Fiscal helpers ────────────────────────────────────────────────────────

    private function is_order_oss( WC_Order $order ) {
        if ( ! Verifactu_Infoal::get_option( 'usa_oss', 0 ) ) {
            return false;
        }
        $country = $order->get_billing_country();
        $shop_country = WC()->countries->get_base_country();
        
        $eu_countries = WC()->countries->get_european_union_countries();
        
        // OSS = EU country, not shop country, and NO VAT number (B2C)
        $vat_number = $order->get_meta( '_billing_vat_number' ) ?: $order->get_meta( '_billing_nif' );
        
        if ( in_array( $country, $eu_countries, true ) && $country !== $shop_country && empty( $vat_number ) ) {
            return true;
        }
        return false;
    }

    private function is_export_invoice( WC_Order $order ) {
        $country = $order->get_billing_country();
        $shop_country = WC()->countries->get_base_country();
        $eu_countries = WC()->countries->get_european_union_countries();

        // Export = Non-EU country
        if ( ! in_array( $country, $eu_countries, true ) ) {
            // Check for special territories like Canary Islands (IC)
            if ( $country === 'ES' && in_array( $order->get_billing_state(), array( 'TF', 'GC', 'CE', 'ML' ), true ) ) {
                 // Canary Islands, Ceuta, Melilla are treated as exports from Peninsula
                 if ( ! Verifactu_Infoal::get_option( 'territorio_especial', 0 ) ) {
                     return true;
                 }
            }
            if ( $country !== 'ES' ) {
                return true;
            }
        }
        return false;
    }

    private function is_b2b_intracommunity( WC_Order $order ) {
        $country = $order->get_billing_country();
        $shop_country = WC()->countries->get_base_country();
        $eu_countries = WC()->countries->get_european_union_countries();
        
        $vat_number = $order->get_meta( '_billing_vat_number' ) ?: $order->get_meta( '_billing_nif' );

        // B2B Intra = EU country, not shop country, HAS VAT number
        if ( in_array( $country, $eu_countries, true ) && $country !== $shop_country && ! empty( $vat_number ) ) {
            return true;
        }
        return false;
    }

    // ── Invoice numbering ─────────────────────────────────────────────────────

    public function get_formatted_invoice_number( WC_Order $order ) {
        $engine = Verifactu_Infoal::get_option( 'invoice_engine', 'wp_overnight' );
        
        $invoice_number = '';
        
        if ( $engine === 'wp_overnight' ) {
            $invoice_number = $order->get_meta( '_wcpdf_invoice_number' );
            if ( empty( $invoice_number ) ) {
                $invoice_number = $order->get_meta( '_wc_pdf_invoice_number' ); // SkyVerge fallback
            }
        }

        if ( empty( $invoice_number ) ) {
            // Fallback: usar el ID o número de pedido original
            $invoice_number = $order->get_order_number();
        }

        return $invoice_number;
    }

    public function get_formatted_refund_number( WC_Order $order ) {
        $engine = Verifactu_Infoal::get_option( 'invoice_engine', 'wp_overnight' );
        
        $refund_number = '';
        
        if ( $engine === 'wp_overnight' ) {
            $refund_number = $order->get_meta( '_wcpdf_credit_note_number' ); 
        }
         
        if ( empty( $refund_number ) ) {
            // Fallback
            $refund_number = 'A' . date( 'y' ) . '-' . $order->get_id();
        }

        return $refund_number;
    }

    // ── DB helpers ────────────────────────────────────────────────────────────

    public function get_status_for_order( $order_id, $type = 'invoice' ) {
        global $wpdb;
        $table = $type === 'invoice' ? 'verifactu_order_invoice' : 'verifactu_order_refund';
        $id_col = $type === 'invoice' ? 'id_order_invoice' : 'id_refund';

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}$table WHERE $id_col = %d", $order_id ), ARRAY_A );
    }

    private function handle_alta_response( $result, $order_id, $tipo, $payload ) {
        global $wpdb;
        
        $table = $tipo === 'alta' ? 'verifactu_order_invoice' : 'verifactu_order_refund';
        $id_field = $tipo === 'alta' ? 'id_order_invoice' : 'id_refund';
        $table_name = $wpdb->prefix . $table;
        
        $invoice_number = isset( $payload['_invoice_number'] ) ? $payload['_invoice_number'] : '';
        
        // Determine id_order
        $id_order_parent = $order_id;
        if ( $tipo === 'abono' ) {
            $refund_obj = wc_get_order( $order_id );
            if ( $refund_obj ) {
                $id_order_parent = $refund_obj->get_parent_id();
            }
        }

        // Check if row already exists
        $existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE $id_field = %d", $order_id ) );

        if ( isset( $result['response'] ) && $result['response'] === 'OK' ) {
            
            $update_data = array(
                'id_order'    => $id_order_parent,
                $id_field     => $order_id,
                'estado'      => 'pendiente',
                'id_reg_fact' => isset( $result['id_reg_fact'] ) ? (int)$result['id_reg_fact'] : 0,
                'urlQR'       => isset( $result['urlQR'] ) ? $result['urlQR'] : '',
                'apiMode'     => isset( $result['apiMode'] ) ? $result['apiMode'] : 'prod',
                'invoice_number' => $invoice_number,
                'date_add'    => current_time( 'mysql' )
            );

            if ( $existing_id ) {
                unset( $update_data['date_add'] );
                $wpdb->update( $table_name, $update_data, array( 'id' => $existing_id ) );
            } else {
                $wpdb->insert( $table_name, $update_data );
            }

            return array( 'success' => true, 'id_reg_fact' => $update_data['id_reg_fact'], 'urlQR' => $update_data['urlQR'] );
            
        } else {
            
            $error_msg = isset( $result['error'] ) ? $result['error'] : 'Error desconocido de la API';
            
            $error_data = array(
                'id_order'                          => $id_order_parent,
                $id_field                           => $order_id,
                'estado'                            => 'api_error',
                'verifactuEstadoEnvio'              => 'Error',
                'verifactuEstadoRegistro'           => 'Error API',
                'verifactuDescripcionErrorRegistro' => $error_msg,
                'invoice_number'                    => $invoice_number,
                'date_add'                          => current_time( 'mysql' )
            );

            if ( $existing_id ) {
                unset( $error_data['date_add'] );
                $wpdb->update( $table_name, $error_data, array( 'id' => $existing_id ) );
            } else {
                $wpdb->insert( $table_name, $error_data );
            }

            return array( 'success' => false, 'error' => $error_msg );
        }
    }
}
