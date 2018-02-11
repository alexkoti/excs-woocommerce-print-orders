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

/**
 * Hooks
 * 
 */
add_action( 'admin_footer-edit.php', array('Excs_Print_Orders', 'footer') );    // Adicionar botões de impressão nas páginas de pedido
add_action( 'wp_ajax_excs_print_orders', 'excs_print_orders_ajax_init' );       // Iniciar página de impressão

/**
 * Inicializar classe e exibir página de preview
 * 
 */
function excs_print_orders_ajax_init(){
    $excs_print_orders = new Excs_Print_Orders();
    $excs_print_orders->ajax();
}

/**
 * 
 * 
 * @todo:
 *  - verificar nível de usuário
 *  - admin page para configurar opções
 * 
 */
class Excs_Print_Orders {
    
    /**
     * Ação do formulário, que irá recarregar a página com as novas configurações, é o mesmo name da action ajax
     * 
     */
    private static $action = 'excs_print_orders';
    
    /**
     * IDs dos pedidos a serem impressos
     * 
     */
    protected $order_ids = array();
    
    /**
     * Quantidade de slots para pular, em caso de não precisar imprimir desde a primeira etiqueta
     * 
     */
    protected $offset = 0;
    
    /**
     * Quantidade de etiquetas por folha, vai depender do layout da página
     * 
     */
    protected $per_page = 0;
    
    /**
     * Tamanhos de papael disponíveis
     * 
     */
    protected $papers = array(
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
    protected $paper = false;
    
    /**
     * Layouts de etiquetas individuais, Largura X Altura
     * Lista de modelos pré-determinados que poderão ser escolhidos.
     * Começar a lista com divisões simples e modelos de etiquetas adesivas pimaco
     * 
     */
    protected $layouts = array(
        'percentage' => array(
            'name' => 'Simples',
            'items' => array(
                '2x2' => array(
                    'name'         => '2x2',
                    'paper'        => 'A4',
                    'per_page'     => 4,
                    'page_margins' => '0 0 0 0',
                    'width'        => '50%',
                    'height'       => '400px',
                    'item_margin'  => '0 0 0 0',
                ),
            ),
        ),
        'pimaco' => array(
            'name' => 'Pimaco',
            'items' => array(
                '6183' => array(
                    'name'         => '6183 (10 etiquetas)',
                    'paper'        => 'Letter',
                    'page_margins' => '10mm 0 0 3.5mm',
                    'per_page'     => 10,
                    'width'        => '101.6mm',
                    'height'       => '50.8mm',
                    'item_margin'  => '0 5.0mm 0 0',
                ),
                '6082' => array(
                    'name'         => '6082 (14 etiquetas)',
                    'paper'        => 'Letter',
                    'page_margins' => '19mm 0 0 6mm',
                    'per_page'     => 14,
                    'width'        => '101.6mm',
                    'height'       => '33.9mm',
                    'item_margin'  => '0',
                ),
                '8099F' => array(
                    'name'         => '8099F (10 etiquetas)',
                    'paper'        => 'Letter',
                    'page_margins' => '19mm 19mm 0 19mm',
                    'per_page'     => 10,
                    'width'        => '77.79mm',
                    'height'       => '46.56mm',
                    'item_margin'  => '0 83px 0 0',
                ),
            ),
        ),
    );
    
    /**
     * Layout da etiqueta individual
     * 
     */
    protected $layout = array();
    
    /**
     * Layouts default
     * 
     */
    protected $layout_default = array(
        'name'         => 'custom',
        'paper'        => 'A4',
        'per_page'     => 10,
        'page_margins' => '0 0 0 0',
        'width'        => '50%',
        'height'       => '100px',
        'item_margin'  => '0 0 0 0',
    );
    
    /**
     * Array de imagens em base64, para serem usadas nas etiquetas individuais
     * 
     */
    protected $images = array(
        'logo' => false,
    );
    
    /**
     * Etiqueta individual atual
     * 
     */
    protected $label = array();
    
    /**
     * Configuração da página do admin
     * 
     */
    protected $admin_title = '';
    
    protected $individual_buttons = true;
    
    protected $layout_select = true;
    
    protected $print_invoice = true;
    
    /**
     * Configuração da impressão
     * 
     */
    protected $config = array(
        'paper'              => 'A4',  // tipo de papel
        'per_page'           => 10,
        'layout' => array(
            'group' => 'percentage',
            'item' => '2x2',
        ),
        'images' => array(
            'logo' => false,
        ),
        'admin' => array(
            'title'              => 'Imprimir Etiquetas de endereços dos pedidos',
            'individual_buttons' => true,       // botões de impressão individuais para cada pedido
            'layout_select'      => true,       // habilitar dropdown para seleção de layout, como modelos de etiquetas pimaco
            'print_invoice'      => true,       // imprimir página de declaração de contepúdo dos correios
        ),
    );
    
    function __construct(){
        
        if( isset($_GET['ids']) ){
            $this->order_ids = explode(',', $_GET['ids']);
        }
        
        $custom_config = apply_filters( 'excs_print_orders_config', $this->config );
        $this->config = wp_parse_args( $custom_config, $this->config );
        
        // adicionar layouts extras
        $this->set_layouts();
        
        // definir layout
        if( isset( $this->layouts[ $this->config['layout']['group'] ]['items'][ $this->config['layout']['item'] ] ) ){
            $this->layout = $this->layouts[ $this->config['layout']['group'] ]['items'][ $this->config['layout']['item'] ];
        }
        
        // definir papel
        $this->paper = $this->layout['paper'];
        
        // quantidade de etiquetas por página
        $this->per_page = $this->layout['per_page'];
        
        // definir etiqueta individual
        $this->label = array(
            'width'        => $this->layout['width'],
            'height'       => $this->layout['height'],
            'item_margin'  => $this->layout['item_margin'],
        );
        
        // definir imagens personalizadas
        if( is_array($this->config['images']) ){
            $this->images = wp_parse_args( $this->config['images'], $this->images );
        }
        
        // definir configurações da página do admin
        $this->admin_title = $this->config['admin']['title'];
    }
    
    public function ajax(){
        
        $sender = false;
        
        ?><!DOCTYPE HTML>
        <html lang="pt_BR">
        <head>
            <meta charset="UTF-8">
            <title><?php echo $this->admin_title; ?></title>
            <?php echo $this->css_base(); ?>
            <?php echo $this->css_preview(); ?>
            <?php echo $this->css_print(); ?>
        </head>
        <body>
            <h1 class="no-print"><?php echo $this->admin_title; ?></h1>
            
            <form action="<?php echo admin_url('/'); ?>" method="get" class="no-print">
                <input type="hidden" name="action" value="<?php echo self::$action; ?>" />
                <input type="hidden" name="ids" value="<?php echo implode(',', $this->order_ids); ?>" />
                <?php if( $sender == 1 ){ ?>
                <fieldset>
                    <legend>Imprimir endereços dos pedidos</legend>
                    <button type="submit" name="sender" value="0">Imprimir destinatários</button>
                </fieldset>
                <?php } else { ?>
                <fieldset>
                    <legend>Imprimir endereços dos pedidos</legend>
                    Definir início da impressão: 
                    <input type="number" name="offset" value="<?php echo $this->offset; ?>" size="2" min="0" max="<?php echo (int)$this->per_page - 1; ?>" /> 
                    <button type="submit" name="sender" value="0">Atualizar</button>
                </fieldset>
                <fieldset>
                    <legend>OU Imprimir apenas remetente Excelsior</legend>
                    <button type="submit" name="sender" value="1">Remetente Excelsior</button>
                </fieldset>
                <?php } ?>
            </form>
            
            <p class="no-print"><a href="javascript: window.print();">IMPRIMIR</a></p>
            
            <h2 class="no-print">Preview:</h2>
            
            <?php
            if( $sender == 0 ){
                $this->print_pages();
            }
            else{
                $this->print_sender();
            }
            ?>
            
            <?php 
            pre( $this->config, 'excs_print_orders_config', false );
            pre( $this, 'Excs_Print_Orders', false );
            ?>
            
        </body>
        </html>
        <?php
        
        die();
    }
    
    function print_pages(){
        
    }
    
    function print_sender(){
        
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
    
    /**
     * Definir layouts disponíveis e adicionais custom
     * 
     */
    protected function set_layouts(){
        // layouts filtrado com customs
        $layouts = apply_filters( 'excs_print_orders_layouts', $this->layouts );
        
        if( is_array($layouts) and !empty($layouts) ){
            // resetar layouts
            $this->layouts = array();
            
            // readicionar layouts
            foreach( $layouts as $slug => $layout ){
                // apenas com nome de grupo e itens definidos
                if( isset($layout['name']) && isset($layout['items']) and !empty($layout['items']) ){
                    $this->add_layout_group( $slug, $layout['name'] );
                    foreach( $layout['items'] as $ls => $args ){
                        $args = wp_parse_args( $args, $this->layout_default );
                        $this->add_layout_item( $slug, $ls, $args );
                    }
                }
            } 
        }
    }
    
    protected function add_layout_group( $slug, $name ){
        if( !isset( $this->layouts[$slug] ) ){
            $this->layouts[$slug] = array(
                'name' => $name,
                'items' => array(),
            );
        }
    }
    
    protected function add_layout_item( $group, $slug, $args ){
        if( isset( $this->layouts[$group] ) && !isset( $this->layouts[$group]['items'][$slug] ) ){
            $this->layouts[$group]['items'][$slug] = array(
                'name'         => $args['name'],
                'paper'        => $args['paper'],
                'page_margins' => $args['page_margins'],
                'per_page'     => $args['per_page'],
                'width'        => $args['width'],
                'height'       => $args['height'],
                'item_margin'  => $args['item_margin'],
            );
        }
    }
    
    public static function footer(){
        global $typenow;
        if( $typenow != 'shop_order' ){
            return;
        }
        
        $url = add_query_arg(array('action' => self::$action), admin_url('admin-ajax.php'));
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


