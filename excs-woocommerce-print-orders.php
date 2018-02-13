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
 *  - validador de CSS
 *  - adicionar opção de google-fonts
 *  - tradução para países
 * 
 */
class Excs_Print_Orders {
    
    /**
     * Ação do formulário, que irá recarregar a página com as novas configurações, é o mesmo name da action ajax
     * 
     */
    private static $action = 'excs_print_orders';
    
    /**
     * Definir se é para imprimir apenas o remetente loja
     * 
     */
    private $print_sender = 0;
    
    /**
     * Permitir a opção de imprimir o remetente
     * 
     */
    private $allow_print_sender = false;
    
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
            'name'         => 'A4',
            'width'        => '210',
            'height'       => '297',
            'unit'         => 'mm',
        ),
        'Letter' => array(
            'name'         => 'Letter',
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
                    'height'       => '50%',
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
        'allow_print_sender' => false,
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
        'css' => array(
            'base'    => '',
            'preview' => '',
            'print'   => '',
            'file'    => '',
        ),
    );
    
    function __construct(){
        
        if( isset($_GET['ids']) ){
            $this->order_ids = explode(',', $_GET['ids']);
        }
        
        $custom_config = apply_filters( 'excs_print_orders_config', $this->config );
        $this->config = array_replace_recursive( $this->config, $custom_config );
        
        // definir se é impressão de remetente
        $this->allow_print_sender = $this->config['allow_print_sender'];
        if( isset($_GET['print_sender']) && $this->allow_print_sender == true ){
            $this->print_sender = boolval( $_GET['print_sender'] );
        }
        
        // adicionar layouts extras
        $this->set_layouts();
        
        // definir layout
        if( isset( $this->layouts[ $this->config['layout']['group'] ]['items'][ $this->config['layout']['item'] ] ) ){
            $this->layout = $this->layouts[ $this->config['layout']['group'] ]['items'][ $this->config['layout']['item'] ];
        }
        
        // definir papel
        $this->paper = $this->papers[ $this->layout['paper'] ];
        
        if( $this->config['layout']['group'] == 'percentage' ){
            $divider = str_replace('%', '', $this->layout['height']);
            $this->layout['height'] = ( ($divider / 100) * $this->paper['height'] ) . 'mm';
        }
        
        // quantidade de etiquetas por página
        $this->per_page = $this->layout['per_page'];
        
        // definir etiqueta individual
        $this->label = array(
            'width'        => $this->layout['width'],
            'height'       => $this->layout['height'],
            'item_margin'  => $this->layout['item_margin'],
        );
        
        // definir offset
        $this->offset = isset($_GET['offset']) ? $_GET['offset'] : 0;
        if( $this->offset > ($this->per_page - 1) ){
            $this->offset = ($this->per_page - 1);
        }
        
        // definir imagens personalizadas
        if( is_array($this->config['images']) ){
            $this->images = wp_parse_args( $this->config['images'], $this->images );
        }
        
        // definir configurações da página do admin
        $this->admin_title = $this->config['admin']['title'];
    }
    
    public function ajax(){
        
        ?><!DOCTYPE HTML>
        <html lang="pt_BR">
        <head>
            <meta charset="UTF-8">
            <title><?php echo $this->admin_title; ?></title>
            <?php
            echo $this->css_base();
            echo $this->css_preview();
            echo $this->css_print();
            if( !empty($this->config['css']['file']) ){
                echo "<link rel='stylesheet' href='{$this->config['css']['file']}' />";
            }
            ?>
        </head>
        <body>
            <h1 class="no-print"><?php echo $this->admin_title; ?></h1>
            
            <form action="<?php echo admin_url( 'admin-ajax.php' ); ?>" method="get" class="no-print">
                <input type="hidden" name="action" value="<?php echo self::$action; ?>" />
                <input type="hidden" name="ids" value="<?php echo implode(',', $this->order_ids); ?>" />
                <?php if( $this->print_sender == 0 ){ ?>
                <fieldset>
                    <legend>Imprimir endereços dos pedidos</legend>
                    Definir início da impressão: 
                    <input type="number" name="offset" value="<?php echo $this->offset; ?>" size="2" min="0" max="<?php echo (int)$this->per_page - 1; ?>" /> 
                    <button type="submit" name="print_sender" value="0">Atualizar</button>
                </fieldset>
                <?php if( $this->allow_print_sender == true ){ ?>
                <fieldset>
                    <legend>Trocar para imprimir apenas remetente</legend>
                    <button type="submit" name="print_sender" value="1">Imprimir remetente</button>
                </fieldset>
                <?php } ?>
                <?php } else { ?>
                <fieldset>
                    <legend>Trocar para imprimir endereços dos pedidos</legend>
                    <button type="submit" name="print_sender" value="0">Imprimir destinatários</button>
                </fieldset>
                <?php } ?>
            </form>
            
            <p class="no-print"><a href="javascript: window.print();" class="btn btn-print">IMPRIMIR</a></p>
            
            <h2 class="no-print" id="preview-title">Preview:</h2>
            
            <?php
            if( $this->print_sender == 0 ){
                $this->print_pages();
            }
            else{
                $this->print_sender();
            }
            ?>
            
            <div class="no-print">
            <?php 
            pre( $this->config, 'excs_print_orders_config', false );
            pre( $this, 'Excs_Print_Orders', false );
            ?>
            </div>
            
        </body>
        </html>
        <?php
        
        die();
    }
    
    function print_pages(){
        
        echo "<div class='paper paper-{$this->paper['name']}'>";
            $total = 0;
            $cel = 1;
            if( $this->offset > 0 ){
                for( $i = 1; $i <= $this->offset; $i++ ){
                    echo '<div class="order empty"><span>vazio</span></div>';
                    if( $cel == 2 ){
                        //echo '<hr />';
                        $cel = 1;
                    }
                    else{
                        $cel++;
                    }
                    $total++;
                }
            }
            
            foreach( $this->order_ids as $id ){
                echo "<div class='order layout-{$this->layout['name']}''>";
                $this->print_order( $id );
                echo '</div>';
                if( $cel == 2 ){
                    $cel = 1;
                }
                else{
                    $cel++;
                }
                $total++;
                
                if( $total % $this->per_page == 0 ){
                    echo "</div><div class='paper paper-{$this->paper['name']}'>";
                }
            }
            
            $empty = ($this->per_page - ($this->offset + count($this->order_ids)));
            if( $empty > 0 ){
                for( $n = 1; $n <= $empty; $n++ ){
                    echo "<div class='order empty paper-{$this->paper['name']} layout-{$this->layout['name']}''><span>vazio</span></div>";
                    if( $cel == 2 ){
                        $cel = 1;
                    }
                    else{
                        $cel++;
                    }
                    $total++;
                }
            }
            //pal($total);
        echo '</div>';
    }
    
    function print_order( $id ){
        include_once( 'vendors/php-barcode-generator/src/BarcodeGenerator.php');
        include_once( 'vendors/php-barcode-generator/src/BarcodeGeneratorPNG.php');
        
        $order = new WC_Order( $id );
        
        $address = $this->get_address( $order );
        $address = $this->validate_address( $address );
        //pre($address);
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        $barcode = base64_encode($generator->getBarcode($address['cep'], $generator::TYPE_CODE_128, 1, 50));
        
        $alert = '';
        
        $shipping = $order->get_items( 'shipping' );
        foreach( $shipping as $method ){
            if( strpos($method['method_id'], 'correios-impresso-normal') !== false ){
                $alert = '<div class="aviso"><div><strong>Impresso Fechado</strong></div><div>Pode ser aberto <br />pela ECT</div><div>CORREIOS</div></div>';
            }
        }
        
        $output = "
        <div class='order-inner'>
            <div class='destinatario'>
                <div class='barcode'>
                    <img src='data:image/png;base64,{$barcode}' /><br />
                    {$address['cep']}
                </div>
                <div class='address'>
                    <strong>Destinatário<br /></strong>
                    <span class='name'>{$address['nome']}<br /></span>
                    <span class='street'>{$address['logradouro']}{$address['complemento']}<br /></span>
                    <span class='company'>{$address['empresa']}</span>
                    <span class='neighbor'>{$address['bairro']}<br /></span>
                    <span class='city-state'>{$address['cidade']} / {$address['uf']}<br /></span>
                    <span class='zip'>{$address['cep']}</span>
                </div>
            </div>
            {$alert}
        </div>";
        echo apply_filters( 'excs_print_orders_customer_label',  $output, $order, $address, $barcode, $alert );
    }
    
    function get_address( $order ){
        
        $order_data = $order->get_data();
        $order_meta_data = $order->get_meta_data();
        //pre($order_data, 'order_data', false);
        //pre($order_meta_data, 'order_meta_data', false);
        //pre($order_data['shipping'], '$order_data[shipping]', false);
        
        if( empty( $order_data['shipping']) ){
            $number       = $this->get_address_meta_data( $order_meta_data, '_billing_number' );
            $neighborhood = $this->get_address_meta_data( $order_meta_data, '_billing_neighborhood' );
            $address = array(
                'nome'           => "{$order_data['billing']['first_name']} {$order_data['billing']['last_name']}",
                'empresa'        => empty($order_data['billing']['company']) ? '' : " - {$order_data['billing']['company']}",
                'logradouro'     => "{$order_data['billing']['address_1']} {$number}",
                'complemento'    => empty($order_data['billing']['address_2']) ? '' : ", {$order_data['billing']['address_2']}",
                'bairro'         => empty($neighborhood) ? '' : "{$neighborhood}",
                'cidade'         => $order_data['billing']['city'],
                'uf'             => empty($order_data['billing']['state']) ? '' : " - {$order_data['billing']['state']}",
                'cep'            => $order_data['billing']['postcode'],
            );
        }
        else{
            $number       = $this->get_address_meta_data( $order_meta_data, '_shipping_number' );
            $neighborhood = $this->get_address_meta_data( $order_meta_data, '_shipping_neighborhood' );
            $address = array(
                'nome'           => "{$order_data['shipping']['first_name']} {$order_data['shipping']['last_name']}",
                'empresa'        => empty($order_data['shipping']['company']) ? '' : " - {$order_data['shipping']['company']}",
                'logradouro'     => "{$order_data['shipping']['address_1']} {$number}",
                'complemento'    => empty($order_data['shipping']['address_2']) ? '' : ", {$order_data['shipping']['address_2']}",
                'bairro'         => empty($neighborhood) ? '' : "{$neighborhood}",
                'cidade'         => $order_data['shipping']['city'],
                'uf'             => empty($order_data['shipping']['state']) ? '' : " - {$order_data['shipping']['state']}",
                'cep'            => $order_data['shipping']['postcode'],
            );
        }
        return $address;
    }
    
    /**
     * Validar dados do endereço para certificar de que não existem campos faltantes.
     * Exibe um texto de alerta destacado para a visualização no navegador. A versão impressa não exibe o alerta.
     * 
     */
    function validate_address( $address ){
        $optional = array(
            'empresa',
            'complemento',
        );
        foreach( $address as $key => $value ){
            if( !in_array($key, $optional) ){
                $cleanned = trim($value);
                if( empty($cleanned) or ($key == 'logradouro' and strlen($value) < 4) ){
                    $address[ $key ] = "<span class='empty-data' style='font-size:12pt;color:red;text-transform:uppercase;'>[{$key} VAZIO]</span>{$value}";
                }
            }
        }
        return $address;
    }
    
    function get_address_meta_data( $meta_data, $key ){
        foreach( $meta_data as $md ){
            $d = $md->get_data();
            if( $d['key'] == $key ){
                return $d['value'];
            }
        }
    }
    
    function print_sender(){
        $logo = '';
        $store_info = $this->get_sender();
        
        $address = array();
        $address[] = "<span class='name'>{$store_info['blogname']}</span>";
        $address[] = "<span class='street'>{$store_info['woocommerce_store_address']}</span>";
        if( !empty($store_info['woocommerce_store_address_2']) ){
            $address[] = "<span class='neighbor'>{$store_info['woocommerce_store_address_2']}</span>";
        }
        $address[] = "<span class='zip'>{$store_info['woocommerce_store_postcode']}</span>";
        $address[] = "<span class='city-state'>{$store_info['woocommerce_store_city']}/{$store_info['state']} - Brasil</span>";
        
        $address = implode( '<br />', $address );
        
        if( !empty($this->images['logo']) ){
            $logo = "<div class='logo'><img src='{$this->images['logo']}' alt='' /></div>";
        }
        
        echo "<div class='paper paper-{$this->paper['name']}'>";
        for( $i = 1; $i <= $this->per_page; $i++ ){
            $output = "
            <div class='order-inner'>
                {$logo}
                <div class='remetente'>
                    <div class='address'>
                        <strong>Remetente<br /></strong>
                        {$address}
                    </div>
                </div>
            </div>";
            $output = apply_filters( 'excs_print_orders_shop_label',  $output, $store_info, $this );
            echo "<div class='order paper-{$this->paper['name']} layout-{$this->layout['name']}'>{$output}</div>";
        }
        echo '</div>';
    }
    
    /**
     * Montar dados do remetente
     * 
     */
    protected function get_sender(){
        
        $store_info = array(
            'blogname'                    => '',
            'woocommerce_store_address'   => '',
            'woocommerce_store_address_2' => '',
            'woocommerce_store_postcode'  => '',
            'woocommerce_store_city'      => '',
        );
        foreach( $store_info as $k => $v ){
            $store_info[ $k ] = get_option( $k );
        }
        
        $_country            = wc_get_base_location();
        $store_info['state'] = $_country['state'];
        
        return $store_info;
    }
    
    /**
     * CSS comum usado tanto para o preview quanto para impressão
     * 
     */
    protected function css_base(){
        ?>
        <style type="text/css" id="css-base">
        /* CSS common, both print and preview */
        body {
            font-family: arial, sans-serif;
            font-size: 10.5pt;
        }
        
        .paper {
            width: <?php echo $this->paper['width']; ?>mm;
            height: <?php echo $this->paper['height']; ?>mm;
            margin: 10px auto;
            margin: 10px 0;
            box-sizing: border-box;
            padding: <?php echo $this->layout['page_margins']; ?>;
        }
        
        .order {
            float: left;
            position: relative;
            width: <?php echo $this->layout['width']; ?>;
            height: <?php echo $this->layout['height']; ?>;
            margin: <?php echo $this->layout['item_margin']; ?>;
            position: relative;
        }
        
        .order-inner {
            padding: 2mm;
            position: relative;
        }
        
        .barcode {
            display: inline-block;
            font-size: 10pt;
            text-align: center;
        }
        
        .aviso {
            border: 2px solid #000;
            text-align:center;
            font-size: 8pt;
            clear: both;
            padding: 0 5px;
            width: 110px;
        }
        .aviso div {
            margin: 8px 0;
        }
        
        .empty {
            text-align: center;
        }
        
        hr {
            clear: both;
            display: block;
            margin: 0;
            visibility: hidden;
            width: 100%;
        }
        
        <?php echo $this->config['css']['base']; ?>
        
        </style>
        <?php
    }
    
    /**
     * CSS exclusivo para preview
     * 
     */
    protected function css_preview(){
        ?>
        <style type="text/css" id="css-preview">
        /* CSS preview only */
        body {
            margin: 20px auto;
            width: 250mm;
        }
        
        .paper {
            outline: 1px dotted green;
        }
        
        .order {
            outline: 1px dotted red;
        }
        
        fieldset {
            border: 1px solid #0085ba;
            margin: 0 0 30px;
        }
        
        button, .btn {
            display: inline-block;
            text-decoration: none;
            font-size: 13px;
            line-height: 26px;
            height: 28px;
            margin: 0;
            padding: 0 10px 1px;
            cursor: pointer;
            border-width: 1px;
            border-style: solid;
            -webkit-appearance: none;
            -webkit-border-radius: 3px;
            border-radius: 3px;
            white-space: nowrap;
            -webkit-box-sizing: border-box;
            -moz-box-sizing: border-box;
            box-sizing: border-box;
            background: #0085ba;
            border-color: #0073aa #006799 #006799;
            -webkit-box-shadow: 0 1px 0 #006799;
            box-shadow: 0 1px 0 #006799;
            color: #fff;
            text-decoration: none;
            text-shadow: 0 -1px 1px #006799, 1px 0 1px #006799, 0 1px 1px #006799, -1px 0 1px #006799;
        }
        
        .btn-print {
            background-color: green;
        }
        
        input[type=text], input[type=number]{
            border: 1px solid #ddd;
            box-shadow: inset 0 1px 2px rgba( 0, 0, 0, 0.07 );
            background-color: #fff;
            color: #32373c;
            line-height: 26px;
            text-align: right;
            outline: none;
            transition: 0.05s border-color ease-in-out;
        }
        
        <?php echo $this->config['css']['preview']; ?>
        
        </style>
        <?php
    }
    
    /**
     * CSS exclusivo para impressão
     * 
     */
    protected function css_print(){
        ?>
        <style type="text/css" id="css-print">
        /* CSS print only */
        @page {
            size: <?php echo $this->paper['name']; ?>;
            margin: 0;
        }
        @media print {
            html, body {
                height: auto;
                margin: 0;
                width: <?php echo $this->paper['width']; ?>;
            }
            
            .paper {
                height: auto !important;
                width: auto !important;
                margin: auto !important;
                padding: auto !important;
                margin: 0;
                border: initial;
                border-radius: initial;
                width: initial;
                min-height: initial;
                box-shadow: initial;
                background: initial;
                page-break-after: always;
                overflow: hidden;
            }
            
            .order {
                outline: none;
                outline: 1px dotted #ccc;
            }
            
            .empty span {
                display: none;
            }
            
            .no-print {
                display: none;
            }
            
            .empty-data {
                display: none;
            }
        }
        
        <?php echo $this->config['css']['print']; ?>
        
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
    
    /**
     * Adicionar grupo de layout
     * 
     */
    protected function add_layout_group( $slug, $name ){
        if( !isset( $this->layouts[$slug] ) ){
            $this->layouts[$slug] = array(
                'name' => $name,
                'items' => array(),
            );
        }
    }
    
    /**
     * Adicionar layout dentro de grupo
     * 
     */
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
                if( $('.column-order_number .excs-order-items').length ){
                    $('.column-order_number .excs-order-items').each(function( index ){
                        var id = $(this).closest('tr').attr('id').replace('post-', '');
                        $('<a href="<?php echo $url; ?>&ids=' + id + '" class="button print-barcode" target="_blank" title="imprimir etiqueta individual">Etiqueta </a>').insertAfter( $(this) );
                    });
                }
                else{
                    $('mark.tips').each(function( index ){
                        var id = $(this).closest('tr').attr('id').replace('post-', '');
                        $('<a href="<?php echo $url; ?>&ids=' + id + '" class="button print-barcode" target="_blank" title="imprimir etiqueta individual"></a>').insertAfter( $(this) );
                    });
                }
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


