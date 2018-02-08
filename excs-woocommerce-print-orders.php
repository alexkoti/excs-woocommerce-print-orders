<?php
/**
 * Plugin Name: Excs WooCommerce Print Orders
 * Plugin URI:  http://github.com/alexkoti/excs-woocommerce-print-orders
 * Description: Allow multiple pickup locations shipping method.
 * Author:      Alex Koti
 * Author       URI: http://alexkoti.com/
 * Version:     1.0.0
 * License:     GPLv2 or later
 * Text Domain: excs-woocommerce-print-orders
 * Domain Path: languages/
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

add_action( 'admin_footer-edit.php', array('Excs_Print_Orders', 'footer') );    // Adicionar botões de impressão nas páginas de pedido
add_action( 'wp_ajax_excs_print_orders', 'excs_print_orders_ajax_init' );      // Iniciar página de impressão

function excs_print_orders_ajax_init(){
    $excs_print_orders = new Excs_Print_Orders();
    $excs_print_orders->ajax();
}

class Excs_Print_Orders {
    
    /**
     * IDs dos pedidos a serem impressos
     * 
     */
    var $ids = false;
    
    /**
     * Quantidade de slots para pular, em caso de não precisar imprimir desde a primeira etiqueta
     * 
     */
    var $offset = 0;
    
    /**
     * Quantidade de etiquetas por folha, vai depender do layout da página
     * 
     */
    var $per_page = 4;
    
    /**
     * Tamanhos de papael disponíveis
     * 
     */
    var $papers = array(
        'A4' => array(
            'width'        => '210',
            'height'       => '297',
            'unit'         => 'mm',
        ),
        'letter' => array(
            'width'        => '216',
            'height'       => '279',
            'unit'         => 'mm',
        ),
    );
    
    /**
     * Papel utilizado atualmente
     * 
     */
    var $current_paper = false;
    
    /**
     * Layouts de etiquetas individuais, Largura X Altura
     * Lista de modelos pré-determinados que poderão ser escolhidos.
     * Começar a lista com divisões simples e modelos de etiquetas adesivas pimaco
     * 
     */
    var $layouts = array(
        'percentage' => array(
            'name' => 'Simples',
            'items' => array(
                '2x2' => array(
                    'name'         => '2x2',
                    'per_page'     => 4,
                ),
            ),
        ),
        'pimaco' => array(
            'name' => 'Pimaco',
            'items' => array(
                '6183' => array(
                    'name'         => '6183 (10 etiquetas)',
                    'per_page'     => 10,
                    'width'        => '101.6mm',
                    'height'       => '50.8mm',
                    'page_margins' => '10mm 0 0 3.5mm',
                    'item_margin'  => '0 5.0mm 0 0',
                ),
                '6082' => array(
                    'name'         => '6082 (14 etiquetas)',
                    'per_page'     => 14,
                    'width'        => '101.6mm',
                    'height'       => '33.9mm',
                    'page_margins' => '19mm 0 0 6mm',
                    'item_margin'  => '0',
                ),
                '8099F' => array(
                    'name'         => '8099F (10 etiquetas)',
                    'per_page'     => 10,
                    'width'        => '77.79mm',
                    'height'       => '46.56mm',
                    'page_margins' => '19mm 19mm 0 19mm',
                    'item_margin'  => '0 83px 0 0',
                ),
            ),
        ),
    );
    
    /**
     * Layout da etiqueta individual
     * 
     */
    var $current_layout = false;
    
    /**
     * Array de imagens em base64, para serem usadas nas etiquetas individuais
     * 
     */
    var $images = array(
        'logo' => false,
    );
    
    /**
     * Configuração da impressão
     * 
     */
    var $config = array(
        'individual_buttons' => true,       // botões de impressão individuais para cada pedido
        'layout_select'      => true,       // habilitar dropdown para seleção de layout, como modelos de etiquetas pimaco
        'print_invoice'      => true,       // imprimir página de declaração de contepúdo dos correios
        'paper'              => 'A4',       // tipo de papel, conforme
        'per_page'           => 10,
        'label' => array(
            'width'        => '100mm',
            'height'       => '130mm',
            'page_margins' => '8mm 0 0 8mm',
            'item_margin'  => '0 0 0 0',
        ),
        'images' => array(
            'logo' => false,
        ),
    );
    
    function __construct(){
        
    }
    
    public function ajax(){
        
        pre( $this, 'Excs_Print_Orders' );
        
        die();
    }
    
    /**
     * CSS comum usado tanto para o preview quanto para impressão
     * 
     */
    protected function css_base(){
        ?>
        <style type="text/css">
        /* CSS common, both print and preview */
        body {
            font-family: arial, sans-serif;
        }
        </style>
        <?php
    }
    
    /**
     * CSS exclusivo para preview
     * 
     */
    protected function css_preview(){
        ?>
        <style type="text/css">
        /* CSS preview only */
        body {
            margin: 20px auto;
            width: 250mm;
        }
        </style>
        <?php
    }
    
    /**
     * CSS exclusivo para impressão
     * 
     */
    protected function css_print(){
        ?>
        <style type="text/css">
        /* CSS print only */
        @page {
            size: Letter;
            margin: 0;
        }
        @media print {
            html, body {
                width: 216mm;
                height: auto;
            }
        }
        </style>
        <?php
    }
    
    public static function footer(){
        global $typenow;
        if( $typenow != 'shop_order' ){
            return;
        }
        
        $url = add_query_arg(array('action' => 'excs_print_orders'), admin_url('admin-ajax.php'));
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($){
                // add/update querystring
                // @link http://stackoverflow.com/a/6021027
                function updateQueryStringParameter(uri, key, value) {
                    var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
                    var separator = uri.indexOf('?') !== -1 ? "&" : "?";
                    if (uri.match(re)) {
                        return uri.replace(re, '$1' + key + "=" + value + '$2');
                    }
                    else {
                        return uri + separator + key + "=" + value;
                    }
                }
                
                $('input:checkbox[name="post[]"], #cb-select-all-1').on('change', function(){
                    var ids_arr = [];
                    $('input:checkbox[name="post[]"]:checked').each(function() {
                        ids_arr.push(this.value);
                    });
                    var url = updateQueryStringParameter( $('#excs-print-orders-button').attr('href'), 'ids', ids_arr.join(',') );
                    $('#excs-print-orders-button').attr('href', url);
                });
                $('<a href="<?php echo $url; ?>" class="button" target="_blank" id="excs-print-orders-button">Imprimir Pedidos Selecionados</a>').insertAfter('#post-query-submit');
                
                // botão individual
                $('mark.tips').each(function( index ){
                    var id = $(this).closest('tr').attr('id').replace('post-', '');
                    $('<a href="<?php echo $url; ?>&ids=' + id + '" class="button print-barcode" target="_blank" title="imprimir etiqueta individual"></a>').insertAfter( $(this) );
                });
            });
        </script>
        <style type="text/css">
            /**
             * Botão de imprimir selecionados
             * 
             */
            #excs-print-orders-button {
                display: inline-block;
                margin: 1px 8px 0 0;
            }
            
            /**
             * Botão de imprimir pedido individual
             * 
             */
            .wp-core-ui .print-barcode {
                margin: 10px 0 0;
                padding: 1px 7px 0;
            }
            .wp-core-ui .print-barcode:after {
                font-family: WooCommerce;
                content: '\e006';
            }
            @media only screen and (max-width: 782px) {
                .wp-core-ui .print-barcode {
                    float: left;
                    margin: 0 0 0 10px;
                }
                .post-type-shop_order .wp-list-table .column-order_status mark {
                    float: left;
                }
            }
        </style>
        <?php
    }
    
}


