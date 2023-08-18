<?php
/**
 * Plugin Name: Excs WooCommerce Print Orders
 * Plugin URI:  http://github.com/alexkoti/excs-woocommerce-print-orders
 * Description: Imprimir etiquetas para pedidos do WooCommerce
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
 * Função para debug
 * 
 */
if( !function_exists('pre') ){
    function pre($var=false,$legend='',$opened=true,$global_controls=false){$pre=PRE::init();$pre->pre($var,$legend,$opened,$global_controls);}function pal($var=false,$legend=''){$pre=PRE::init();$pre->pal($var,$legend);}function sep($message=''){echo"<div style='background:red;color:#fff;font:12px normal arial, sans-serif;height:10px;line-height:10px;margin:50px 0;padding:5px;'>{$message}</div>";}class PRE{var $controls_visible=false;private static $instance;public static function init(){if(empty(self::$instance)){self::$instance=new PRE();}return self::$instance;}private function __construct(){$this->do_css();$this->do_js();}private function do_css(){echo "<style type='text/css'>#pre_box_controls{background:#fefcf5;border:1px solid #333;clear:both;color:#333;cursor:pointer;font:12px monospace;line-height:90%;margin:5px;padding:5px;text-align:left}.pre_box{background:#fefcf5;border:1px solid #333;clear:both;color:#333;font:12px monospace;line-height:90%;margin:5px;position:relative;text-align:left}.boros_element_duplicate_group.layout_block .pre_box{max-width:750px;overflow:hidden}.boros_element_duplicate_group.layout_table .pre_box{max-width:450px;overflow:hidden}.pre_box_head{background:#ededed;color:#da1c23;cursor:pointer;font-size:14px;font-weight:400;margin:0 !important;padding:5px}.pre_box_footer{font-size:11px;background:#ededed;color:#999;margin:0;padding:5px}.pre_box_bool{font-size:14px;color:#333;margin:0;padding:5px}.pre_box_footer strong,.pre_box_bool span{color:#da1c23}.pre_box pre{border:0;font-size:12px;white-space:pre;margin:0;padding:5px}.pal_box{background:#f1f19b;border:1px solid #333;border-left:10px groove red;clear:both;color:#333;font:400 14px monospace;line-height:90%;margin:5px;padding:5px;text-align:left}.pre_box_content{overflow:auto}.pre_box_content_opened{display:block}.pre_box_content_closed{display:none}#wpwrap #debug_admin_vars{padding:0 20px 10px 165px}.pre_box{background:#f4f4f4;border:1px solid #dfdfdf;border-radius:3px}</style>";}private function do_js(){echo "<script type='text/javascript'>function toggle_pre( el ){el.className = ( el.className != 'pre_box_content pre_box_content_closed' ? 'pre_box_content pre_box_content_closed' : 'pre_box_content pre_box_content_opened' );}function pre_box_toggle( ctrl ){if( ctrl.className == 'pre_box_control_opened' ){ctrl.className = 'pre_box_control_closed';var content_class = 'pre_box_content pre_box_content_closed';}else{ctrl.className = 'pre_box_control_opened';var content_class = 'pre_box_content pre_box_content_opened';}var elems = document.getElementsByTagName('div'), i;for (i in elems){if((' ' + elems[i].className + ' ').indexOf(' ' + 'pre_box_content' + ' ') > -1){elems[i].className = content_class;}}}</script>";}function esc_html($var){if(function_exists('esc_html')){return esc_html($var);}else{return htmlentities($var,ENT_QUOTES,'UTF-8',false);}}function multidimensional_array_map( $func, $arr ){if( function_exists('multidimensional_array_map') ){return multidimensional_array_map( $func, $arr );}else{if( !is_array($arr) )return $arr;$newArr = array();foreach( $arr as $key => $value ){if( is_scalar( $value ) ){$nvalue = call_user_func( $func, $value );}else{$nvalue = $this->multidimensional_array_map( $func, $value );}$newArr[ $key ] = $nvalue;}return $newArr;}}function pal($message,$var_name=false){$var=($var_name!=false)?"<strong>{$var_name}</strong> &gt; &gt; &gt; ":'';echo "<div class='pal_box'>".$var.$this->esc_html($message)."</div>\n";}public function pre($var=false,$legend='',$opened=true,$global_controls=false){$id=uniqid('pre_');$js="toggle_pre(document.getElementById('{$id}'));";$click='onclick="'.$js.'";';if($opened===true){$content_class='pre_box_content pre_box_content_opened';}else{$content_class='pre_box_content pre_box_content_closed';}if($global_controls==true and $this->controls_visible==false){echo '<div id="pre_box_controls" class="pre_box_control_opened" onclick="pre_box_toggle(this)">abrir/fechar todos</div>';$this->controls_visible=true;}echo "<div class='pre_box'>\n";echo($legend=='')?'':"<p class='pre_box_head' {$click}>{$legend}</p>\n";echo"<div id='{$id}' class='{$content_class}'>\n";if(is_object($var)||is_array($var)){echo "<pre>\n";if(is_array($var)){print_r($this->multidimensional_array_map(array($this,'esc_html'),$var));}else{print_r($var);}echo "\n</pre>\n";echo "<p class='pre_box_footer'>TOTAL: <strong>".count($var).'</strong></p>';}else{$size='';$type=gettype($var);if($type=='boolean')$var=($var==false)?'FALSE':'TRUE';if($type=='string'){$len=strlen($var);$size=" ({$len})";}echo "<p class='pre_box_bool'>\n\t<em>".$type."</em> : \n\t<span>\n\t\t".$this->esc_html($var)."\n\t</span>".$size."\n</p>\n";}echo "\n</div></div>\n";}}
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
 *  - remetente opcional
 *  - verificar nível de usuário
 *  - admin page para configurar opções
 *  - validador de CSS
 *  - adicionar opção de google-fonts
 *  - invoice padrão mais simples + adicionar invoice correios separadamente
 *  - separar exibição dos items agrupados ou separados
 *  - tradução
 * 
 */
class Excs_Print_Orders {
    
    /**
     * Exbir debug
     * 
     */
    protected $debug = false;
    
    /**
     * URL do plugin
     * 
     */
    private $plugin_url = '';
    
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
                    'page_margins' => '12mm 0 0 3.5mm',
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
                'A4356' => array(
                    'name'         => 'A4356 (33 etiquetas)',
                    'paper'        => 'A4',
                    'page_margins' => '8.8mm 0mm 0mm 7.2mm',
                    'per_page'     => 33,
                    'width'        => '63.5mm',
                    'height'       => '25.4mm',
                    'item_margin'  => '0 0 0 0',
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
    
    protected $invoice_group_items = false;
    
    protected $invoice_group_name = '';

    protected $invoice_group_empty_rows = 0;
    
    /**
     * Configuração da impressão
     * 
     */
    protected $config = array(
        'debug'              => false,
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
            'title'                    => 'Imprimir Etiquetas de endereços dos pedidos',
            'individual_buttons'       => true,       // botões de impressão individuais para cada pedido
            'layout_select'            => true,       // habilitar dropdown para seleção de layout, como modelos de etiquetas pimaco
            'print_invoice'            => true,       // imprimir página de declaração de contepúdo dos correios
            'invoice_group_items'      => false,      // agrupar items na declaração
            'invoice_group_name'       => '',         // nome para agrupamento na declaração
            'invoice_group_empty_rows' => 5,          // quantidade de linhas em branco após a listagem resumida
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
        
        include_once "vendor/autoload.php";

        global $wp_locale;
        $this->locale = $wp_locale;
        
        $this->plugin_url = plugin_dir_url( __FILE__ );
        
        // definir os pedidos
        $this->set_orders();
        
        $custom_config = apply_filters( 'excs_print_orders_config', $this->config );
        $this->config = array_replace_recursive( $this->config, $custom_config );
        
        // definir status do debug
        $this->debug = $this->config['debug'];
        
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
        $this->admin_title              = $this->config['admin']['title'];
        $this->individual_buttons       = $this->config['admin']['individual_buttons'];
        $this->layout_select            = $this->config['admin']['layout_select'];
        $this->print_invoice            = $this->config['admin']['print_invoice'];
        $this->invoice_group_items      = $this->config['admin']['invoice_group_items'];
        $this->invoice_group_name       = $this->config['admin']['invoice_group_name'];
        $this->invoice_group_empty_rows = $this->config['admin']['invoice_group_empty_rows'];
        
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
                
                <?php if( $this->print_action == 'recipient' ){ ?>
                <fieldset>
                    <legend>Offset:</legend>
                    <p>Pular <input type="number" name="offset" value="<?php echo $this->offset; ?>" size="2" min="0" max="<?php echo (int)$this->per_page - 1; ?>" /> itens no começo da impressão. <button type="submit" name="print_action" value="recipient">atualizar</button></p>
                </fieldset>
                <?php } ?>
            </form>
            
            <?php if( $this->print_action != '' ){ ?>
            <p class="no-print"><a href="javascript: window.print();" class="btn btn-print">IMPRIMIR</a></p>
            <h2 class="no-print" id="preview-title">Preview:</h2>
            <?php } ?>
            
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
                    echo '<p>Escolha o tipo de impressão</p>';
                    break;
            }
            ?>
            
            
            <?php
            if( $this->debug == true ){
                echo '<div class="no-print">';
                pre( $this->config, 'DEBUG: excs_print_orders_config (abrir)', false );
                pre( $this, 'DEBUG: Excs_Print_Orders (abrir)', false );
                echo '</div>';
            }
            ?>
            
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
            <button type="submit" name="print_action" value="recipient" id="print-btn-recipient">Destinatários</button>
            <button type="submit" name="print_action" value="sender" id="print-btn-sender">Remetente</button>
            <button type="submit" name="print_action" value="invoice" id="print-btn-invoice">Declaração</button>
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

        $this->orders = apply_filters( 'excs_print_orders', $this->orders );
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
                echo "<div class='order layout-{$this->layout['name']}'>";
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
        //include_once( 'vendors/php-barcode-generator/src/BarcodeGenerator.php');
        //include_once( 'vendors/php-barcode-generator/src/BarcodeGeneratorPNG.php');
        
        $address = $order->address_print;
        
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        $barcode = base64_encode($generator->getBarcode($address['cep'], $generator::TYPE_CODE_128, $this->barcode_config['width_factor'], $this->barcode_config['height']));
        
        $alert = '';
        
        $shipping = $order->get_items( 'shipping' );
        foreach( $shipping as $method ){
            if( strpos($method['method_id'], 'correios-impresso-normal') !== false || strpos($method['method_id'], 'free_shipping') !== false ){
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
        echo apply_filters( 'excs_print_orders_customer_label', $output, $order, $address, $barcode, $alert );
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
        $address = apply_filters( 'excs_print_orders_customer_address', $address, $order );
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
        echo apply_filters( 'excs_print_orders_single_invoice_output_start', '' );
        $i = 0;
        foreach( $this->orders as $id => $order ){
            $invoice = $this->set_invoice( $order );
            echo apply_filters( 'excs_print_orders_single_invoice_output', "<div class='paper invoice'><div class='invoice-inner'>{$invoice}</div></div>", $invoice, $id, $i);
            $i++;
        }
        echo apply_filters( 'excs_print_orders_single_invoice_output_end', '' );
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
        //pre($order, 'order', false);
        //pre($order->get_total(), 'get_total', true);
        //pre($invoice_info);
        //pre($this->store_info);
        //pre($order->address_print);
        //pre($this->locale);
        
        $group_title    = $this->invoice_group_name;
        $quantity_total = 0;
        $weight_total   = 0;
        $subtotal       = 0;
        $items          = $order->get_items();
        $order_items    = array();
        foreach( $items as $id => $product ){
            $p              = $product->get_product();
            $product_data   = $product->get_data();
            $weight         = (float) $p->get_weight() * $product_data['quantity'];
            $product_data   = $product->get_data();
            $quantity_total += $product_data['quantity'];
            $weight_total   += $weight;
            $subtotal       += (double) $product->get_subtotal();
            $order_items[] = array(
                'name'     => $product_data['name'],
                'quantity' => $product_data['quantity'],
                'price'    => $product->get_subtotal(),
                'weight'   => $weight,
            );
        }
        // arredondar apenas para gramas
        if( get_option('woocommerce_weight_unit') == 'g' ){
            $weight_total = round($weight_total);
        }
        $order_items = apply_filters( 'excs_print_orders_invoice_order_items', $order_items );
        //pre($subtotal, 'subtotal');
        //pre($order_items, 'order_items');
        
        ob_start();
        ?>
        <div class="invoice-page">
            <h1 class="invoice-logo">
                <img src="<?php echo $this->plugin_url; ?>/assets/img/logo-correios.svg" alt="" class="correios-logo" />
                Declaração de Conteúdo
            </h1>
            <!-- remetente -->
            <table class="invoice-sender" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="sender"><strong class="label">REMETENTE:</strong> <span class="value"><?php echo $this->store_info['blogname']; ?></span></td>
                    <td class="document"><strong class="label">CPF/CNPJ:</strong> <span class="value"><?php echo $this->store_info['woocommerce_store_cpf_cnpj']; ?></span></td>
                </tr>
                <tr>
                    <td colspan="2" class="address"><strong class="label">ENDEREÇO:</strong> <span class="value"><?php echo "{$this->store_info['woocommerce_store_address']}, {$this->store_info['woocommerce_store_address_2']}"; ?></span></td>
                </tr>
                <tr>
                    <td class="city-state"><strong class="label">CIDADE/UF:</strong> <span class="value"><?php echo "{$this->store_info['woocommerce_store_city']} / {$this->store_info['state']}"; ?></span></td>
                    <td class="zip-code"><strong class="label">CEP:</strong> <span class="value"><?php echo $this->store_info['woocommerce_store_postcode']; ?></span></td>
                </tr>
            </table>
            <!-- destinatário -->
            <table class="invoice-client" cellpadding="0" cellspacing="0">
                <tr>
                    <td colspan="2" class="receiver"><strong class="label">DESTINATÁRIO:</strong> <span class="value"><?php echo $order->address_print['nome']; ?></span></td>
                </tr>
                <tr>
                    <td colspan="2" class="document"><strong class="label">CPF/CNPJ:</strong> <span class="value"><?php echo $order->get_meta('_billing_cpf'); ?></span></td>
                </tr>
                <tr>
                    <td colspan="2" class="address"><strong class="label">ENDEREÇO:</strong> <span class="value"><?php echo "{$order->address_print['logradouro']}{$order->address_print['complemento']}, {$order->address_print['bairro']}"; ?></span></td>
                </tr>
                <tr>
                    <td class="city-state"><strong class="label">CIDADE/UF:</strong> <span class="value"><?php echo "{$order->address_print['cidade']} / {$order->address_print['uf']}"; ?></span></td>
                    <td class="zip-code"><strong class="label">CEP:</strong> <span class="value"><?php echo $order->address_print['cep']; ?></span></td>
                </tr>
            </table>
            <!-- lista de itens -->
            <table class="invoice-order-items" cellpadding="0" cellspacing="0">
                <tr>
                    <th colspan="3" class="invoice-title">IDENTIFICAÇÃO DOS BENS</th>
                </tr>
                <tr>
                    <th class="label">DISCRIMINAÇÃO DO CONTEÚDO</th>
                    <th class="label">QUANTIDADE</th>
                    <th class="label">PESO</th>
                </tr>

                <?php if( $this->invoice_group_items == true ){ ?>
                    <tr>
                        <td class="item-value group-title"><?php echo $group_title; ?></td>
                        <td class="item-value group-quantity"><?php echo $quantity_total; ?></td>
                        <td class="item-value group-weight"><?php echo wc_format_weight($weight_total); ?></td>
                    </tr>
                    <?php for($i = 0; $i < $this->invoice_group_empty_rows; $i++){ ?>
                    <tr>
                        <td class="item-value empty">&nbsp;</td>
                        <td class="item-value empty">&nbsp;</td>
                        <td class="item-value empty">&nbsp;</td>
                    </tr>
                    <?php } ?>
                <?php } else { ?>
                <?php foreach( $order_items as $item ){ ?>
                    <tr class="order-items">
                        <td class="item-value item-name"><?php echo $item['name']; ?></td>
                        <td class="item-value item-quantity"><?php echo $item['quantity']; ?></td>
                        <td class="item-value item-weight"><?php echo wc_format_weight($item['weight']); ?></td>
                    </tr>
                <?php } ?>
                <?php } ?>

                <tr>
                    <td colspan="2" class="label order-total">VALOR TOTAL <?php echo wc_price($subtotal); ?></td>
                    <td>&nbsp;</td>
                </tr>
            </table>
            <!-- declaração -->
            <table class="invoice-disclaimer" cellpadding="0" cellspacing="0">
                <tr>
                    <th class="invoice-title">DECLARAÇÃO</th>
                </tr>
                <tr>
                    <td>
                        <div class="text">
                             Declaro, não ser pessoa física ou jurídica, que realize, com habitualidade ou em volume que 
                             caracterize intuito comercial, operações de circulação de mercadoria, ainda que estas se 
                             iniciem no exterior, que o conteúdo declarado e não está sujeito à tributação, e que sou o 
                             único responsável por eventuais penalidades ou danos decorrentes de informações inverídicas. 
                        </div>
                        
                        <div class="signature-date">
                            <div class="date">
                                <span class="underline"><?php echo $this->store_info['woocommerce_store_city']; ?></span>, 
                                <span class="underline"><?php echo $invoice_info['signature']['day']; ?></span> de 
                                <span class="underline"><?php echo $invoice_info['signature']['month']; ?></span> de 
                                <span class="underline"><?php echo $invoice_info['signature']['year']; ?></span>
                            </div>
                            <div class="signature">
                                Assinatura do Declarante/Remetente
                            </div>
                        </div>
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
                    $('.order-preview').each(function( index ){
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
                margin: 10px 10px 0 0;
                padding: 1px 7px 0;
            }
            .wp-core-ui .order-preview + .print-barcode {
                margin: 0 10px 0 0;
                float: left;
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

        .invoice .invoice-logo {
            font-size: 18px;
        }
        
        .invoice .correios-logo {
            max-width: 40mm;
        }
        
        .invoice h1 {
            font-size: 18pt;
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
            border: 1px solid #000;
            font-weight: bold;
            padding: 2mm;
            text-align: center;
        }
        
        .invoice table th.label {
            background-color: transparent;
        }
        
        .invoice table td {
            border: 1px solid #000;
            padding: 1mm;
        }
        
        .invoice table td.label {
            font-weight: bold;
            text-align: center;
        }
        
        .invoice table td .label {
            font-size: 80%;
        }
        
        .invoice table.invoice-disclaimer td,
        .invoice table.invoice-obs td {
            font-size: 9.5pt;
            padding: 2mm;
        }
        
        .invoice table.invoice-disclaimer .text {
            text-indent: 12mm;
        }
        
        .invoice .signature-date {
            border: none;
            margin: 0 auto;
            padding: 0;
            width: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .invoice .signature-date .signature {
            border-top: 1px solid;
            margin-top: 30px;
            padding-top: 5px;
        }
        
        .invoice table.invoice-obs ol {
            list-style-type: upper-roman;
            margin: 1mm 0 0;
            padding: 0 0 0 3mm;
        }
        
        .invoice table.invoice-obs ol li {
            padding: 0;
        }
        </style>
        <?php
        echo '<style type="text/css">' . $this->config['css']['base'] . '</style>';
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
        
        </style>
        <?php
        echo '<style type="text/css">' . $this->config['css']['preview'] . '</style>';
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
            /* É vital que as medidas do body sejam iguais ao tamanho do papel, para não ocorrer redimensionamento no navegador */
            html, body {
                height: <?php echo $this->paper['height']; ?>mm;
                margin: 0;
                width: <?php echo $this->paper['width']; ?>mm;
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
                outline: 1px dotted #ccc;
                outline: none;
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
}


