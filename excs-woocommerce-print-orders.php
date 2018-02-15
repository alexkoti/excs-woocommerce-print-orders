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
 *  - invoice padrão mais simples + adicionar invoice correios separadamente
 *  - separar exibição dos items agrupados ou separados
 *  - tradução para países
 * 
 */
class Excs_Print_Orders {
    
    /**
     * Cópia do $wp_locale
     * 
     */
    protected $locale = array();
    
    /**
     * Ação do formulário, que irá recarregar a página com as novas configurações, é o mesmo name da action ajax
     * 
     */
    private static $action = 'excs_print_orders';
    
    /**
     * Qual elemento será impresso:
     * - 'recipient'    endereço destinatário
     * - 'sender'       endereço remetente
     * - 'invoice'      invoice(declaração correios)
     * 
     */
    protected $print_action = 'sender';
    protected $print_sender = 0;
    
    /**
     * Informações da loja/remetente
     * 
     */
    protected $store_info = array();
    
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
                    'page_margins' => '10mm 10mm 10mm 10mm',
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
        'correios' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAmgAAACPCAIAAADBU7ANAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAxhpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYwIDYxLjEzNDM0MiwgMjAxMC8wMS8xMC0xODowNjo0MyAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNSIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDo2MkExNkVCQTExMDQxMUU4QUZBNkNGNTJFODVFNTQwMyIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDo2MkExNkVCQjExMDQxMUU4QUZBNkNGNTJFODVFNTQwMyI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOjYyQTE2RUI4MTEwNDExRThBRkE2Q0Y1MkU4NUU1NDAzIiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOjYyQTE2RUI5MTEwNDExRThBRkE2Q0Y1MkU4NUU1NDAzIi8+IDwvcmRmOkRlc2NyaXB0aW9uPiA8L3JkZjpSREY+IDwveDp4bXBtZXRhPiA8P3hwYWNrZXQgZW5kPSJyIj8+BSXzfwAAMHVJREFUeNrsnXlcTtkfx3tapFWitIqU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',
    );
    
    protected $barcode_config = array(
        'width_factor' => 0,
        'height'       => 0,
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
    
    protected $invoice_group_items = true;
    
    protected $invoice_group_name = '';
    
    /**
     * Configuração da impressão
     * 
     */
    protected $config = array(
        'paper'              => 'A4',  // tipo de papel
        'per_page'           => 10,
        'print_action'       => '',
        'layout' => array(
            'group' => 'percentage',
            'item' => '2x2',
        ),
        'images' => array(
            'logo' => false,
        ),
        'admin' => array(
            'title'               => 'Imprimir Etiquetas de endereços dos pedidos',
            'individual_buttons'  => true,       // botões de impressão individuais para cada pedido
            'layout_select'       => true,       // habilitar dropdown para seleção de layout, como modelos de etiquetas pimaco
            'print_invoice'       => true,       // imprimir página de declaração de contepúdo dos correios
            'invoice_group_items' => true,       // agrupar items na declaração
            'invoice_group_name'  => '',         // nome para agrupamento na declaração
        ),
        'css' => array(
            'base'    => '',
            'preview' => '',
            'print'   => '',
            'file'    => '',
        ),
        'barcode_config' => array(
            'width_factor' => 1,
            'height'       => 50,
        ),
    );
    
    /**
     * Valores permitidos de formulários
     * 
     */
    protected $form_vars = array(
        'print_action' => array(
            'type' => 'in_array',
            'args' => array(
                'recipient',
                'sender',
                'invoice',
            ),
        ),
        'offset' => array(
            'type' => 'natural_number',
        ),
    );
    
    protected $orders = array();
    
    function __construct(){
        global $wp_locale;
        $this->locale = $wp_locale;
        
        // definir os pedidos
        $this->set_orders();
        
        $custom_config = apply_filters( 'excs_print_orders_config', $this->config );
        $this->config = array_replace_recursive( $this->config, $custom_config );
        
        // definir print action
        $this->print_action = $this->config['print_action'];
        $this->set_form_var('print_action', $this->print_action);
        
        // adicionar layouts extras
        $this->set_layouts();
        
        // definir layout
        if( isset( $this->layouts[ $this->config['layout']['group'] ]['items'][ $this->config['layout']['item'] ] ) ){
            $this->layout = $this->layouts[ $this->config['layout']['group'] ]['items'][ $this->config['layout']['item'] ];
        }
        
        // definir papel
        $this->paper = $this->papers[ $this->layout['paper'] ];
        
        // ajustar altura das etiquetas calculando a medida do paper com as margens
        if( $this->config['layout']['group'] == 'percentage' ){
            $margim = str_replace( 'mm', '', explode( ' ', $this->layout['page_margins'] ) );
            $divider = str_replace('%', '', $this->layout['height']);
            $this->layout['height'] = ( ($divider / 100) * ($this->paper['height'] - $margim[0] - $margim[2]) ) . 'mm';
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
        //$this->offset = isset($_GET['offset']) ? $_GET['offset'] : 0;
        $this->set_form_var('offset', 0);
        if( $this->offset > ($this->per_page - 1) ){
            $this->offset = ($this->per_page - 1);
        }
        
        // definir imagens personalizadas
        if( is_array($this->config['images']) ){
            $this->images = wp_parse_args( $this->config['images'], $this->images );
        }
        
        // definir configurações da página do admin
        $this->admin_title         = $this->config['admin']['title'];
        $this->individual_buttons  = $this->config['admin']['individual_buttons'];
        $this->layout_select       = $this->config['admin']['layout_select'];
        $this->print_invoice       = $this->config['admin']['print_invoice'];
        $this->invoice_group_items = $this->config['admin']['invoice_group_items'];
        $this->invoice_group_name  = $this->config['admin']['invoice_group_name'];
        
        // definir configurações do código de barras
        $this->barcode_config = $this->config['barcode_config'];
        
        // definir informações da loja/remtente
        $this->set_sender();
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
                
                <?php $this->print_action_bar(); ?>
                
                <fieldset>
                    <legend>Offset:</legend>
                    <p>Pular <input type="number" name="offset" value="<?php echo $this->offset; ?>" size="2" min="0" max="<?php echo (int)$this->per_page - 1; ?>" /> itens no começo da impressão.</p>
                </fieldset>
            </form>
            
            <p class="no-print"><a href="javascript: window.print();" class="btn btn-print">IMPRIMIR</a></p>
            
            <h2 class="no-print" id="preview-title">Preview:</h2>
            
            <?php
            switch( $this->print_action ){
                case 'recipient':
                    $this->print_pages();
                    break;
                    
                case 'sender':
                    $this->print_sender();
                    break;
                    
                case 'invoice':
                    $this->print_invoices();
                    break;
                    
                default:
                    echo '<h2>Escolha o tipo de impressão</h2>';
                    break;
            }
            ?>
            
            <div class="no-print">
            <?php 
            pre( $this->config, 'DEBUG: excs_print_orders_config (abrir)', false );
            pre( $this, 'DEBUG: Excs_Print_Orders (abrir)', true );
            ?>
            </div>
            
        </body>
        </html>
        <?php
        
        die();
    }
    
    /**
     * Definir variável de formulário
     * Utilizar valor enviado via $_GET ou utilizar valor padrão
     * Validar os dados conforme o tipo.
     * 
     */
    protected function set_form_var( $name, $default = false ){
        $value = isset($_GET[$name]) ? $_GET[$name] : $default;
        $v = false;
        
        if( isset($this->form_vars[$name]) ){
            switch( $this->form_vars[$name]['type'] ){
                case 'in_array':
                    if( in_array( $value, $this->form_vars[$name]['args'] ) ){
                        $this->$name = $value;
                    }
                    break;
                
                case 'natural_number':
                    $int = filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)));
                    $this->$name = ($int == false) ? 0 : $int;
                    break;
                    
                default:
                    break;
            }
        }
        
        return $v;
    }
    
    /**
     * Barra de controle para escolher o tipo de impressão:
     * - 'recipient'    endereço destinatário
     * - 'sender'       endereço remetente
     * - 'invoice'      invoice(declaração correios)
     * 
     */
    protected function print_action_bar(){
        switch( $this->print_action ){
            case 'recipient':
                echo '<h2>Imprimindo destinatários</h2>';
                break;
                
            case 'sender':
                echo '<h2>Imprimindo remetente</h2>';
                break;
                
            case 'invoice':
                echo '<h2>Imprimindo declaração</h2>';
                break;
                
            default:
                echo '<h2>Escolher o tipo de impressão</h2>';
                break;
        }
        ?>
        <fieldset>
            <legend>Escolha o tipo:</legend>
            <button type="submit" name="print_action" value="recipient">Destinatários</button>
            <button type="submit" name="print_action" value="sender">Remetente</button>
            <button type="submit" name="print_action" value="invoice">Declaração</button>
        </fieldset>
        <?php
    }
    
    protected function set_orders(){
        if( isset($_GET['ids']) ){
            $this->order_ids = explode(',', $_GET['ids']);
        }
        
        foreach( $this->order_ids as $id ){
            $order = new WC_Order( $id );
            
            // buscar informações de endereço
            $address = $this->get_address( $order );
            // guardar informações de endereço para serem usadas no invoice
            $order->address_print = $address;
            //pre($address);
            
            // guardar o pedido em orders
            $this->orders[ $id ] = $order;
        }
    }
    
    protected function print_pages(){
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
            
            foreach( $this->orders as $order ){
                echo "<div class='order layout-{$this->layout['name']}''>";
                $this->print_order( $order );
                echo '</div>';
                if( $cel == 2 ){
                    $cel = 1;
                }
                else{
                    $cel++;
                }
                $total++;
                
                if( $total % $this->per_page == 0 && $total != (count($this->order_ids) + $this->offset) ){
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
    
    protected function print_order( $order ){
        include_once( 'vendors/php-barcode-generator/src/BarcodeGenerator.php');
        include_once( 'vendors/php-barcode-generator/src/BarcodeGeneratorPNG.php');
        
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        $barcode = base64_encode($generator->getBarcode($address['cep'], $generator::TYPE_CODE_128, $this->barcode_config['width_factor'], $this->barcode_config['height']));
        
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
    
    protected function get_address( $order ){
        
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
                'uf'             => empty($order_data['shipping']['state']) ? '' : $order_data['shipping']['state'],
                'cep'            => $order_data['shipping']['postcode'],
            );
        }
        $address = $this->validate_address( $address );
        return $address;
    }
    
    /**
     * Validar dados do endereço para certificar de que não existem campos faltantes.
     * Exibe um texto de alerta destacado para a visualização no navegador. A versão impressa não exibe o alerta.
     * 
     */
    protected function validate_address( $address ){
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
    
    protected function get_address_meta_data( $meta_data, $key ){
        foreach( $meta_data as $md ){
            $d = $md->get_data();
            if( $d['key'] == $key ){
                return $d['value'];
            }
        }
    }
    
    protected function print_sender(){
        $logo = '';
        
        $address = array();
        $address[] = "<span class='name'>{$this->store_info['blogname']}</span>";
        $address[] = "<span class='street'>{$this->store_info['woocommerce_store_address']}</span>";
        if( !empty($this->store_info['woocommerce_store_address_2']) ){
            $address[] = "<span class='neighbor'>{$this->store_info['woocommerce_store_address_2']}</span>";
        }
        $address[] = "<span class='zip'>{$this->store_info['woocommerce_store_postcode']}</span>";
        $address[] = "<span class='city-state'>{$this->store_info['woocommerce_store_city']} / {$this->store_info['state']} - Brasil</span>";
        
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
            $output = apply_filters( 'excs_print_orders_shop_label',  $output, $this->store_info, $this );
            echo "<div class='order paper-{$this->paper['name']} layout-{$this->layout['name']}'>{$output}</div>";
        }
        echo '</div>';
    }
    
    /**
     * Montar dados do remetente
     * 
     */
    protected function set_sender(){
        
        $store_info = array(
            'blogname'                    => '',
            'woocommerce_store_address'   => '',
            'woocommerce_store_address_2' => '',
            'woocommerce_store_postcode'  => '',
            'woocommerce_store_city'      => '',
            'woocommerce_store_cpf_cnpj'  => '',
        );
        foreach( $store_info as $k => $v ){
            $store_info[ $k ] = get_option( $k );
        }
        
        $_country = wc_get_base_location();
        $store_info['state'] = $_country['state'];
        
        $this->store_info = $store_info;
    }
    
    /**
     * Imprimir declaração dos correios
     * 
     */
    protected function print_invoices(){
        
        foreach( $this->orders as $id => $order ){
            $invoice = $this->set_invoice( $order );
            echo "<div class='paper invoice'><div class='invoice-inner'>{$invoice}</div></div>";
        }
    }
    
    /**
     * Definir conteúdo da declaração
     * 
     */
    protected function set_invoice( $order ){
        
        $invoice = " ==== {$order->get_id()} ====";
        
        $invoice_info = array(
            'signature' => array(
                'day'      => date('d'),
                'month'    => $this->locale->month_genitive[ date('m') ],
                'year'     => date('Y'),
            ),
        );
        //pre( $invoice_info );
        //pre( $this->store_info );
        //pre( $order->address_print );
        //pre($this->locale);
        
        $product_title = $this->invoice_group_name;
        $quantity_total = 0;
        $items = $order->get_items();
        foreach( $items as $id => $product ){
            $product_data = $product->get_data();
            $quantity_total += $product_data['quantity'];
        }
        
        ob_start();
        ?>
        <div class="invoice-page">
            <h1>
                <img src="<?php echo $this->images['correios']; ?>" alt="" class="correios-logo" />
                Declaração de Conteúdo
            </h1>
            <!-- remetente -->
            <table class="invoice-sender" cellpadding="0" cellspacing="0">
                <tr>
                    <td colspan="2"><strong>REMETENTE:</strong> <?php echo $this->store_info['blogname']; ?></td>
                </tr>
                <tr>
                    <td colspan="2"><strong>CPF/CNPJ:</strong> <?php echo $this->store_info['woocommerce_store_cpf_cnpj']; ?></td>
                </tr>
                <tr>
                    <td colspan="2"><strong>ENDEREÇO:</strong> <?php echo "{$this->store_info['woocommerce_store_address']}, {$this->store_info['woocommerce_store_address_2']}"; ?></td>
                </tr>
                <tr>
                    <td><strong>CIDADE/UF:</strong> <?php echo "{$this->store_info['woocommerce_store_city']} / {$this->store_info['state']}"; ?></td>
                    <td width="200"><strong>CEP:</strong> <?php echo $this->store_info['woocommerce_store_postcode']; ?></td>
                </tr>
            </table>
            <!-- destinatário -->
            <table class="invoice-client" cellpadding="0" cellspacing="0">
                <tr>
                    <td colspan="2"><strong>DESTINATÁRIO:</strong> <?php echo $order->address_print['nome']; ?></td>
                </tr>
                <tr>
                    <td colspan="2"><strong>CPF/CNPJ:</strong> <?php echo $order->get_meta('_billing_cpf'); ?></td>
                </tr>
                <tr>
                    <td colspan="2"><strong>ENDEREÇO:</strong> <?php echo "{$order->address_print['logradouro']}{$order->address_print['complemento']}, {$order->address_print['bairro']}"; ?></td>
                </tr>
                <tr>
                    <td><strong>CIDADE/UF:</strong> <?php echo "{$order->address_print['cidade']} / {$order->address_print['uf']}"; ?></td>
                    <td width="200"><strong>CEP:</strong> <?php echo $order->address_print['cep']; ?></td>
                </tr>
            </table>
            <!-- lista de itens -->
            <table class="invoice-order-items" cellpadding="0" cellspacing="0">
                <tr>
                    <th colspan="3">IDENTIFICAÇÃO DOS BENS</th>
                </tr>
                <tr>
                    <td class="label">DISCRIMINAÇÃO DO CONTEÚDO</td>
                    <td class="label">QUANTIDADE</td>
                    <td class="label">PESO</td>
                </tr>
                <tr>
                    <td><?php echo $product_title; ?></td>
                    <td><?php echo $quantity_total; ?></td>
                    <td>&nbsp;</td>
                </tr>
                <?php for($i = 0; $i <= 5; $i++){ ?>
                <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
                <?php } ?>
                <tr>
                    <td colspan="2" class="label">VALOR TOTAL <?php echo $order->get_formatted_order_total(); ?></td>
                    <td>&nbsp;</td>
                </tr>
            </table>
            <!-- declaração -->
            <table class="invoice-disclaimer" cellpadding="0" cellspacing="0">
                <tr>
                    <th>DECLARAÇÃO </th>
                </tr>
                <tr>
                    <td>
                        <div class="text">
                             Declaro, não ser pessoa física ou jurídica, que realize, com habitualidade ou em volume que 
                             caracterize intuito comercial, operações de circulação de mercadoria, ainda que estas se 
                             iniciem no exterior, que o conteúdo declarado e não está sujeito à tributação, e que sou o 
                             único responsável por eventuais penalidades ou danos decorrentes de informações inverídicas. 
                        </div>
                        
                        <table class="signature-date">
                            <tr>
                                <td><span class="underline"><?php echo $this->store_info['woocommerce_store_city']; ?></span>,</td>
                                <td><span class="underline"><?php echo $invoice_info['signature']['day']; ?></span> de</td>
                                <td><span class="underline"><?php echo $invoice_info['signature']['month']; ?></span> de</td>
                                <td><span class="underline"><?php echo $invoice_info['signature']['year']; ?></span></td>
                                <td>&nbsp;&nbsp;&nbsp;&nbsp;_______________________________</td>
                            </tr>
                            <tr>
                                <td colspan="4">&nbsp;</td>
                                <td>Assinatura do<br />Declarante/Remetente</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <!-- observações -->
            <table class="invoice-obs" cellpadding="0" cellspacing="0">
                <tr>
                    <td><strong>Atenção:</strong> O declarante/remetente é responsável exclusivamente pelas informações declaradas.</td>
                </tr>
                <tr>
                    <td>
                        <strong>OBSERVAÇÕES:</strong>
                        <ol>
                            <li>É Contribuinte de ICMS qualquer pessoa física ou jurídica, que realize, com habitualidade ou em volume 
                            que caracterize intuito comercial, operações de circulação de mercadoria ou prestações de serviços de 
                            transportes interestadual e intermunicipal e de comunicação, ainda que as operações e prestações se 
                            iniciem no exterior (Lei Complementar nº 87/96 Art. 4º).</li>
                            <li>Constitui crime contra a ordem tributária suprimir ou reduzir tributo, ou contribuição social e 
                            qualquer acessório: quando negar ou deixar de fornecer, quando obrigatório, nota fiscal ou documento 
                            equivalente, relativa a venda de mercadoria ou prestação de serviço, efetivamente realizada, ou fornecê-la 
                            em desacordo com a legislação. Sob pena de reclusão de 2 (dois) a 5 (anos), e multa (Lei 8.137/90 Art. 1º, V). 
                            </li>
                        </ol>
                    </td>
                </tr>
            </table>
        </div>
        <?php
        $invoice = ob_get_contents();
        ob_end_clean();
        
        return apply_filters( 'excs_print_orders_invoice', $invoice, $order, $this );
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
        
        .invoice {
            padding: 10mm;
        }
        
        .invoice .correios-logo {
            max-width: 50mm;
        }
        
        .invoice h1 {
            font-size: 20pt;
            margin: 0;
        }
        
        .invoice table {
            border: 2px solid #000;
            border-collapse: collapse;
            margin: 2mm 0;
            width: 100%;
        }
        
        .invoice table th {
            background-color: #d9d9d9;
            font-weight: bold;
            padding: 2mm;
            text-align: center;
        }
        
        .invoice table td {
            border: 1px solid #000;
            padding: 1mm;
        }
        
        .invoice table td.label {
            font-weight: bold;
            text-align: center;
        }
        
        .invoice table.invoice-disclaimer td,
        .invoice table.invoice-obs td {
            font-size: 9.5pt;
            padding: 2mm;
        }
        
        .invoice table.invoice-disclaimer .text {
            text-indent: 12mm;
        }
        
        .invoice table.signature-date {
            border: none;
            margin: 0 auto;
            width: auto;
        }
        
        .invoice table.signature-date td {
            border: none;
            padding: 1mm;
            text-align: center;
        }
        
        .invoice table.signature-date .underline {
            text-decoration: underline;
        }
        
        .invoice table.signature-date .underline:after {
            content: '....';
            color: transparent;
        }
        
        .invoice table.invoice-obs ol {
            list-style-type: upper-roman;
            padding: 0 0 0 5mm;
        }
        
        .invoice table.invoice-obs ol li {
            padding: 0 0 0 7mm;
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
        
        fieldset p {
            margin: 0;
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


