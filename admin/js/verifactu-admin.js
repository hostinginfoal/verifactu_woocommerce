/**
 * VeriFactu InFoAL — Admin JavaScript
 * Equivalent to views/js/back.js in the PrestaShop module.
 *
 * Handles:
 *  - Send alta / abono (AJAX)
 *  - Send anulación (AJAX, with SweetAlert2 confirmation)
 *  - Check NIF (AJAX)
 *  - Check API status
 *  - Validate API token
 *  - Generate Facturae (order + refund)
 *  - Send to FACe
 *  - Download .xsig / .xml
 *  - Check DB
 *  - Send diagnostic
 *
 * TODO-FASE4: Implement all handlers (stubs only)
 */

/* global jQuery, verifactuInFoAL, Swal */
( function( $ ) {
    'use strict';

    // ── Utility: authenticated AJAX call ──────────────────────────────────────
    /**
     * Wrapper around $.post() that always includes the WP nonce.
     *
     * @param {string}   action   wp_ajax action name.
     * @param {Object}   data     POST data (nonce added automatically).
     * @param {Function} success  Success callback receives parsed JSON.
     * @param {Function} error    Error callback receives error message string.
     */
    function vfAjax( action, data, success, error ) {
        var payload = $.extend( {}, data, {
            action: action,
            nonce:  verifactuInFoAL.nonce
        } );

        $.post( verifactuInFoAL.ajaxUrl, payload )
            .done( function( response ) {
                if ( response && response.success ) {
                    if ( typeof success === 'function' ) { success( response.data ); }
                } else {
                    var msg = ( response && response.data && response.data.error )
                        ? response.data.error
                        : verifactuInFoAL.i18n.error_generic;
                    if ( typeof error === 'function' ) { error( msg ); }
                }
            } )
            .fail( function() {
                if ( typeof error === 'function' ) { error( verifactuInFoAL.i18n.error_generic ); }
            } );
    }

    // ── Document ready ────────────────────────────────────────────────────────
    $( function() {

        // ── Send VeriFactu alta ────────────────────────────────────────────────
        // TODO-FASE5: Implement
        $( document ).on( 'click', '.verifactu-btn-send-alta', function( e ) {
            e.preventDefault();
            var orderId = $( this ).data( 'order-id' );
            var tipo    = $( this ).data( 'tipo' ) || 'alta';

            // TODO-FASE5:
            // Swal.fire({
            //     title: verifactuInFoAL.i18n.confirm_send,
            //     icon: 'question',
            //     showCancelButton: true,
            //     confirmButtonText: 'Sí, enviar',
            // }).then( function( result ) {
            //     if ( result.isConfirmed ) {
            //         vfAjax( 'verifactu_send_alta', { order_id: orderId, tipo: tipo },
            //             function( data ) { /* handle success */ },
            //             function( msg  ) { /* handle error   */ }
            //         );
            //     }
            // });
            console.log( '[VeriFactu] send_alta — FASE5 pendiente. Order:', orderId );
        } );

        // ── Send anulación ─────────────────────────────────────────────────────
        // TODO-FASE5: Implement
        $( document ).on( 'click', '.verifactu-btn-anular', function( e ) {
            e.preventDefault();
            var orderId = $( this ).data( 'order-id' );

            // TODO-FASE5:
            // Swal.fire({
            //     title: verifactuInFoAL.i18n.confirm_cancel,
            //     icon: 'warning',
            //     showCancelButton: true,
            // }).then( function( result ) {
            //     if ( result.isConfirmed ) {
            //         vfAjax( 'verifactu_send_anulacion', { order_id: orderId }, ... );
            //     }
            // });
            console.log( '[VeriFactu] send_anulacion — FASE5 pendiente. Order:', orderId );
        } );

        // ── Check API status ───────────────────────────────────────────────────
        // TODO-FASE2: Implement
        $( document ).on( 'click', '#verifactu-btn-check-status', function( e ) {
            e.preventDefault();
            console.log( '[VeriFactu] check_api_status — FASE2 pendiente.' );
        } );

        // ── Test API token ─────────────────────────────────────────────────────
        // TODO-FASE2: Implement
        $( document ).on( 'click', '#verifactu-btn-test-key', function( e ) {
            e.preventDefault();
            console.log( '[VeriFactu] test_key — FASE2 pendiente.' );
        } );

        // ── Check database ─────────────────────────────────────────────────────
        // FASE1 already implemented in PHP — JS just fires the action
        $( document ).on( 'click', '#verifactu-btn-check-db', function( e ) {
            e.preventDefault();
            vfAjax( 'verifactu_check_database', {},
                function( data ) {
                    alert( data.message || 'Base de datos correcta.' );
                },
                function( msg ) {
                    alert( 'Error: ' + msg );
                }
            );
        } );

        // ── Generate Facturae ──────────────────────────────────────────────────
        // TODO-FASE7: Implement
        $( document ).on( 'click', '.verifactu-btn-generate-fe', function( e ) {
            e.preventDefault();
            console.log( '[VeriFactu] generate_facturae — FASE7 pendiente.' );
        } );

        // ── Download .xsig ─────────────────────────────────────────────────────
        // TODO-FASE7: Implement
        $( document ).on( 'click', '.verifactu-btn-download-xsig', function( e ) {
            e.preventDefault();
            console.log( '[VeriFactu] download_facturae — FASE7 pendiente.' );
        } );

        // ── Download .xml ──────────────────────────────────────────────────────
        // TODO-FASE7: Implement
        $( document ).on( 'click', '.verifactu-btn-download-xml', function( e ) {
            e.preventDefault();
            console.log( '[VeriFactu] download_facturae_xml — FASE7 pendiente.' );
        } );

    } );

} )( jQuery );
