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
        'correios' => 'data:image/jpg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAM3AmcDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+g0UhoYBRVW91C30+ISTseThVUZJrP8A+Eosf+eVx/3yP8aVhnF/F3SPECaSde8O6rqcEtsP9Ktra5dUaIZy4UHqvfHUfSvBR448WsQB4l1ck9ALyTn9a+r18R2c7rEkFw7OdoXYOf1rxDxh4R0bRfHNzNproY2USfZVX5baQ5yB2x0IHbP0piLXhLxl4j0X7PJqWoXF+D/r4riQv8pPQE9GA717tY3tvqNlDeWsgkgmUMjDuK+ca9E+Fuo6gbu504K8lgE8wsekLZ4x/vc8e2fWgD1Kiobq6gsrWS6upo4LeJd0ksjBVUepJ6V53efHTwba3Bija/ulBx5sFv8AL/48QT+VKwz0qisDw14z0HxdC8mj3yzPGAZIWBWSPPqp5x79K36QBRRmigAooooAKKKM0AFFFFABRRmigApaSlFNAFFFFMQUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABUVzcR2sDTTNtRRkmpap6nYrqFm0DNtP3lb0NAHF315Jf3TTyHrwq/3R6VWp0sbwytFIpV1OCD2rN1HUGtdqQNtnyG3D+GgDU1fUx4T0jz8r/a12pW3jPWJe7kf5/nXlrOXkaR3LO5LMzHJJPU17dos+j+Krc3F5p1nLfRgLMJYlY+xBI6fyrV/4RrQv+gNp/8A4DJ/hQB4JYWNzql9FZWcZlnlOFUdvUn0A9a958PaHD4f0aGxiwzL80smMb3PU/0HsBVq00vT9PZms7G2tywwxhiVCfyFW6VwPBfiPd6h49+Jtr4JsZzFZ2zKJe437dzuR32rwB659a9R0v4c+EtJsBaQ6FZzDGGkuYhK7+pLMP5cV5RrlyPAPx9Gs36n+z70+Z5mM4R12Mf+AsMkele+xypNGssTq8bgMrochge4I602Bza2Phr4caBqWo21oljZA+fP5YLFm4AUZ9yAB0Ge1eTR+I/ih8SJZbvw6raZpaOVXy5FjAPoZD8zn1xxXffGqOSb4X6gYiSI5YWkA/u+Yv8AUg1b+E19YXnw30qOyZA1tGYp416rICd2frnP40AcHpHj/wAXeBNct9K+IKPJZ3P3LttrNGM43Bk4cDIyD8w4+h6v4oeP9R8Mf2ZpehRRy6nqRPluy79i5CrtHQlieO3Brnf2hruz/snR7JgrXpneVAOSsYXB/Akr9dp9KxvGlvcWnjD4Z212D9pigs0lB67hKoP60Addpeu+NfCHhTWvEHjmXzjEFSzsh5OXcnGS0Y4BJAx2wT6Vl+DdX+KHivVbDWJ7qGz8NzSGSR1jh2+WpOVVTl+SpGT9a6T42f8AJL7z/rvD/wCjBTPBcFxc/AWG3tM/aZdNuEix13EyAfrQBzGp/EHxl421270zwBbhLK2OGvAFy4zgNufhQewxuIGfYVh4t+Jfw9urabxdB/aGkSyiN5P3bEeu11xhsZwG4OK5X4b6b401WwvIfCmvwWCxSBp7d5NjkkYDY2HI4x17V1uo/D74ravYyWOo+IrS5tZcb4pJyQcHI/5Z+ooA6b4n+Pb/AEbwfo+teGb2IR38w2ytEHDRlCw4Yccge9WvHfxJPhHw9p3kRpc63fxK0UTA7VBAy5A68nAAxk/Q1538Q9A1Dwx8IfDmkanJE9xb38uTExZdpEjAAkDsas+KpodL+MHhHUdWIGnGztijv91cbhk/RiGNAEwj+OMi/wBqB5QCN4ts24OP+uZ/kea7HwF8UY/EGlajHrUItdW0yJ5biJFI8yNRywB5BByCvbj149HDKVDAgqeQR0rwnw+bTXf2htXn01Ul07ypVuSvKSDy1RvqC/59aAKln4g+Kvj9rnVvD0wttPjlMaQxSQoFIAO3LcscEZJ457dK9b8ByeLJdAz4vigjvhIVQRgbyg7vtJXJOenbFec3Xwb1/RLya68FeJ3t4ixK27SvEwx/CWXIbHTkD3rpfhJ421TxNb6lpetqrajpbqjzAAGQEsPmA4yChGR1oA9KooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAorl/BfiS88R/8JD9sjgT+zdbudPh8lSN0ce3aWyTluTkjA9q6igDI1fRV1AiWJgk44yejD3rjZfAOsPKzm4tGJOclm5/SvSaKAOC0jwnr2jagl3bzWRIG10Lth17j7td4M4560tFABijFFFAHPeLvBuk+M9LFlqcbgod0M8RCyRN7Eg9e4PFeWp8IfHWkbrXQvGCxWDHhfOlhwP91cj8jXudFAHB+CPh5L4b0bUbHV9Vk1ZNRybiBx+6yRhiM5YkjqcjoOK5C9+CutaPqMl14L8SPZJJw0c0jowHpvQHcPqK9rooA8l8K/BprXWV1vxXqh1a/jcSRoGZk3DoWZuWxxgcD61q+N/AOp+JfHHh7W7O4tI7bTXjaVZWYO22UOdoAI6epFei0UAcp8RPDF54t8HT6PYSwRTySRuHnJCgKwJ6AntVrwRoV14a8Hado95JFJcWsZV3hJKklmPGQD39K6GigDybxJ8HJpNcl13wlrMmk38sjSPGWITc3J2svKgnscj6Uvhj4deNbTxPa61rvi4zm3P+qjd5fMXuh3YAB9ga9YooA4L4qeCdS8caJY2emzWsUkFyZWNwzAEbCOMA85NXvFHgCw8XeG7PTNRdo57RR5VxEASjBcHr1U8ZHHQdK6+igDwr/hT3jqOD+yo/GCf2R93Z50w+X02dPw3Yr0XwX8PdP8FaPc2tlPM93dAefeEKHyBgbRggAZJAOeTzmuwooA8Rm+EfjbTLq4i8P+MWSwndnYTTyxvluSWC5BPqRjNd38O/AEHgTTJ0Ny11f3ZVrmbGFJGcKo64GT15OfwHZ0UAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUVy//AAkl5/wtP/hF/Lg+w/2J/aHmbT5nmef5eM5xtx2xnPeigDH+Fn/M6/8AY133/slegV5/8LP+Z1/7Gu+/9kr0CgAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooA8/8A+bhf+5U/9u6KP+bhf+5U/wDbuigA+Fn/ADOv/Y133/slegV5/wDCz/mdf+xrvv8A2SvQKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooA4f4bPZv/wAJd9jgnix4lvBN50wk3yfJuZcKu1TxhTuI/vGu4rz/AOFn/M6/9jXff+yV6BQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAV9l5/aO/wA+D7D5WPJ8k+Z5mfvb92NuONu3Oed3airFFAHn/wALP+Z1/wCxrvv/AGSvQK8/+Fn/ADOv/Y133/slegUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFAFe+v7PTLOS8v7uC0tY8b5p5BGi5IAyx4GSQPxqxXn/xt/5JDrv/AG7/APpRHXoFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFAHn/AMLP+Z1/7Gu+/wDZK9ArP0rRNO0T7b/Z1v5P267kvbj52bfM+Nzck4zgcDA9q0KACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigDz/AONv/JIdd/7d/wD0ojr0Cs/W9E07xHo8+k6tb/aLGfb5kW9k3bWDDlSCOQDwa0KACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKAPP8A4Wf8zr/2Nd9/7JXoFef/AAs/5nX/ALGu+/8AZK9AoAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKAPP/8Am4X/ALlT/wBu6KP+bhf+5U/9u6KALHw2SzT/AIS77HPPLnxLeGbzoRHsk+Tcq4ZtyjjDHaT/AHRXcV5/8LP+Z1/7Gu+/9kr0CgAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooA5f8A4Ru8/wCFp/8ACUeZB9h/sT+z/L3HzPM8/wAzOMY2475zntRXUUUAef8Aws/5nX/sa77/ANkr0CuH+G1/eX3/AAl32y7nuPI8S3kEPnSF/LjXZtRc9FGTgDgV3FABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUVX+wWf9o/2j9kg+3eV5H2nyx5nl53bN3XbnnHTNFAHD/Cz/mdf+xrvv/ZK9Arh/hs9m/8Awl32OCeLHiW8E3nTCTfJ8m5lwq7VPGFO4j+8a7igAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKK5/xt4n/4Q7whfa/9j+2fZfL/AHHm+Xu3SKn3sHGN2enaugoAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAoqvsvP7R3+fB9h8rHk+SfM8zP3t+7G3HG3bnPO7tRQBw/ws/5nX/sa77/ANkr0CuH+G1heWP/AAl32y0nt/P8S3k8PnRlPMjbZtdc9VODgjg13FABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQB5/8bf8AkkOu/wDbv/6UR16BXD/F+wvNT+Fus2dhaT3d1J5GyGCMyO2J4ycKOTgAn8K7igAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAoryyx8T/EnxDqWujQrbwuLHTdVuNPU3gnEjeWRgnaxB4I545zxV/zfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK87834xf8+/gv87n/GjzfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK87834xf8+/gv87n/GjzfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK87834xf8+/gv87n/GjzfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK87834xf8+/gv87n/GjzfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK87834xf8+/gv87n/GjzfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK87834xf8+/gv87n/GjzfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK87834xf8+/gv87n/GjzfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK87834xf8+/gv87n/GjzfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK87834xf8+/gv87n/GjzfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK87834xf8+/gv87n/GjzfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK87834xf8+/gv87n/GjzfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK87834xf8+/gv87n/GjzfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK87834xf8+/gv87n/GjzfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK87834xf8+/gv87n/GjzfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK87834xf8+/gv87n/GjzfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK87834xf8+/gv87n/GjzfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK87834xf8+/gv87n/GjzfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK87834xf8+/gv87n/GjzfjF/z7+C/wA7n/GgD0SivO/N+MX/AD7+C/zuf8aPN+MX/Pv4L/O5/wAaAPRKK898M+JfGLfEGTwx4pg0Rf8AiVHUEfTRL/z1EYBLt/vcY9OaKAHfCz/mdf8Asa77/wBkr0CvP/hZ/wAzr/2Nd9/7JXoFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQB5/wD83C/9yp/7d0Uf83C/9yp/7d0UAWPhtf3l9/wl32y7nuPI8S3kEPnSF/LjXZtRc9FGTgDgV3Fef/Cz/mdf+xrvv/ZK9AoAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACuH+L9/eaZ8LdZvLC7ntLqPyNk0Ehjdczxg4YcjIJH413Fef/G3/kkOu/8Abv8A+lEdAHoFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABXD/AAgv7zU/hbo15f3c93dSefvmnkMjtieQDLHk4AA/Cu4rz/4Jf8kh0L/t4/8ASiSgD0CiiigAooooAKKKKACiiigAooooAKKKKAK/2Cz/ALR/tH7JB9u8ryPtPljzPLzu2buu3POOmaKsUUAcP8NreK3/AOEu8q9guvM8S3kjeSHHlMdmUbeq/MO+3K88E13Fef8Aws/5nX/sa77/ANkr0CgAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAK4f4v28V38LdZgmvYLKNvIzPOHKJieM8hFZuenAPX05ruK8/wDjb/ySHXf+3f8A9KI6APQKKKKACiiigAooooAKKKKACiiigAooooAKKKKACuH+EFvFafC3RoIb2C9jXz8TwBwj5nkPAdVbjpyB09Oa7ivP/gl/ySHQv+3j/wBKJKAPQKKKKACiiigAooooAKKKKACiiigAooooAr/aJf7R+zfYp/J8rzPteU8vdnGzG7fuxz93bjvniirFFAHn/wALP+Z1/wCxrvv/AGSvQK4f4bXEVx/wl3lWUFr5fiW8jbyS581hsy7b2b5j324XjgCu4oAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACvP/AI2/8kh13/t3/wDSiOvQK4f4v3EVp8LdZnmsoL2NfIzBOXCPmeMclGVuOvBHT04oA7iiiigAooooAKKKKACiiigAooooAKKKKACiiigArz/4Jf8AJIdC/wC3j/0okr0CuH+EFxFd/C3Rp4bKCyjbz8QQFyiYnkHBdmbnryT19OKAO4ooooAKKKKACiiigAooooAKKKKACiiigAoqv9nl/tH7T9tn8nyvL+yYTy92c787d+7HH3tuO2eaKAOH+Fn/ADOv/Y133/slegVw/wANrC8sf+Eu+2Wk9v5/iW8nh86Mp5kbbNrrnqpwcEcGu4oAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACvP/jb/AMkh13/t3/8ASiOvQK4f4v2F5qfwt1mzsLSe7upPI2QwRmR2xPGThRycAE/hQB3FFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABXn/AMEv+SQ6F/28f+lElegVw/wgsLzTPhbo1nf2k9pdR+fvhnjMbrmeQjKnkZBB/GgDuKKKKACiiigAooooAKKKKACiiigAooooAKKr/b7P+0f7O+1wfbvK8/7N5g8zy87d+3rtzxnpmigDh/hZ/wAzr/2Nd9/7JXoFef8Aws/5nX/sa77/ANkr0CgAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAK8/+Nv/ACSHXf8At3/9KI69Arz/AONv/JIdd/7d/wD0ojoA9AooooAKKKKACiiigAooooAKKKKACiiigAooooAK8/8Agl/ySHQv+3j/ANKJK9Arz/4Jf8kh0L/t4/8ASiSgD0CiiigAooooAKKKKACiiigAooooAKKKKACiiigDh/hslmn/AAl32OeeXPiW8M3nQiPZJ8m5VwzblHGGO0n+6K7ivP8A4Wf8zr/2Nd9/7JXoFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAVw/xfSzk+FusrfzzwWp8jfJBCJXX9/HjCllB5x/EPXnpXcV5/wDG3/kkOu/9u/8A6UR0AegUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFcP8IEs4/hboy2E889qPP2STwiJ2/fyZyoZgOc/xH146V3Fef/BL/kkOhf8Abx/6USUAegUUUUAFFFFABRRRQAUUUUAFFFFABRRRQBX33n9o7PIg+w+VnzvOPmeZn7uzbjbjnduznjb3oqxRQB5/8LP+Z1/7Gu+/9kr0CuH+G17Lef8ACXeakC+T4lvIV8mBIsqNmC2wDc3PLNlj3JruKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigArz/42/8AJIdd/wC3f/0ojr0CuH+L97Lp/wALdZuoUgeRPIwJ4EmQ5njHKOCp69xx160AdxRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAV5/8ABL/kkOhf9vH/AKUSV6BXD/CC9l1D4W6NdTJAkj+fkQQJCgxPIOEQBR07Dnr1oA7iiiigAooooAKKKKACiiigAooooAKKKKACiq/2KL+0ft2+fzvK8nb57+XtznPl52bs/wAWN2OM44ooA4f4Wf8AM6/9jXff+yV6BXn/AMLP+Z1/7Gu+/wDZK9AoAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACvP/jb/wAkh13/ALd//SiOvQK8/wDjb/ySHXf+3f8A9KI6APQKKKKACiiigAooooAKKKKACiiigAooooAKKKKACvP/AIJf8kh0L/t4/wDSiSvQK8/+CX/JIdC/7eP/AEokoA9AooooAKKKKACiiigAooooAKKKKACiiigAooooA8/+Fn/M6/8AY133/slegV5/8LP+Z1/7Gu+/9kr0CgAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAK8/+Nv8AySHXf+3f/wBKI69Arz/42/8AJIdd/wC3f/0ojoA9AooooAKKKKACiiigAooooAKKKKACiiigAooooAK8/wDgl/ySHQv+3j/0okr0CvP/AIJf8kh0L/t4/wDSiSgD0CiiigAooooAKKKKACiiigAooooAKKKKACiiigDz/wCFn/M6/wDY133/ALJXoFef/Cz/AJnX/sa77/2SvQKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigArz/wCNv/JIdd/7d/8A0ojr0CvP/jb/AMkh13/t3/8ASiOgD0CiiigAooooAKKKKACiiigAooooAKKKKACiiigArz/4Jf8AJIdC/wC3j/0okr0CvP8A4Jf8kh0L/t4/9KJKAPQKKKKACiiigAooooAKKKKACiiigAooooAKKKKAOH+G1heWP/CXfbLSe38/xLeTw+dGU8yNtm11z1U4OCODXcV5/wDCz/mdf+xrvv8A2SvQKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigArh/i/YXmp/C3WbOwtJ7u6k8jZDBGZHbE8ZOFHJwAT+FdxXn/AMbf+SQ67/27/wDpRHQB6BRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAVw/wgsLzTPhbo1nf2k9pdR+fvhnjMbrmeQjKnkZBB/Gu4rz/4Jf8AJIdC/wC3j/0okoA9AooooAKKKKACiiigAooooAKKKKACiiigCv8Ab7P+0f7O+1wfbvK8/wCzeYPM8vO3ft67c8Z6ZoqxRQB5/wDCz/mdf+xrvv8A2SvQK4f4bJZp/wAJd9jnnlz4lvDN50Ij2SfJuVcM25RxhjtJ/uiu4oAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACvP/AI2/8kh13/t3/wDSiOvQK4f4vpZyfC3WVv554LU+RvkghErr+/jxhSyg84/iHrz0oA7iiiigAooooAKKKKACiiigAooooAKKKKACiiigArz/AOCX/JIdC/7eP/SiSvQK4f4QJZx/C3RlsJ557UefsknhETt+/kzlQzAc5/iPrx0oA7iiiigAooooAKKKKACiiigAooooAKKKKACiq++8/tHZ5EH2Hys+d5x8zzM/d2bcbcc7t2c8be9FAHD/AAs/5nX/ALGu+/8AZK9Arz/4Wf8AM6/9jXff+yV6BQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFef/ABt/5JDrv/bv/wClEdegV5/8bf8AkkOu/wDbv/6UR0AegUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFef/BL/kkOhf8Abx/6USV6BXn/AMEv+SQ6F/28f+lElAHoFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFAHD/AA2vZbz/AIS7zUgXyfEt5CvkwJFlRswW2Abm55Zsse5NdxXn/wALP+Z1/wCxrvv/AGSvQKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigArh/i/ey6f8AC3WbqFIHkTyMCeBJkOZ4xyjgqevccdetdxXn/wAbf+SQ67/27/8ApRHQB6BRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAVw/wgvZdQ+FujXUyQJI/n5EECQoMTyDhEAUdOw569a7ivP8A4Jf8kh0L/t4/9KJKAPQKKKKACiiigAooooAKKKKACiiigAooooAr/Yov7R+3b5/O8rydvnv5e3Oc+XnZuz/FjdjjOOKKsUUAcP8ADayls/8AhLvNeBvO8S3ky+TOkuFOzAbYTtbjlWww7gV3Fef/AAs/5nX/ALGu+/8AZK9AoAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACuH+L9lLqHwt1m1heBJH8jBnnSFBieM8u5Cjp3PPTrXcV5/8AG3/kkOu/9u//AKUR0AegUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFcP8ILKXT/hbo1rM8DyJ5+TBOkyHM8h4dCVPXseOnWu4rz/AOCX/JIdC/7eP/SiSgD0CiiigAooooAKKKKACiiigAooooAKKKKAK/22L+0fsOyfzvK87d5D+XtzjHmY2bs/w53Y5xjmirFFAHn/AMLP+Z1/7Gu+/wDZK9Arh/hs9m//AAl32OCeLHiW8E3nTCTfJ8m5lwq7VPGFO4j+8a7igAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAK8/8Ajb/ySHXf+3f/ANKI69Arh/i+9nH8LdZa/gnntR5G+OCYRO37+PGGKsBzj+E+nHWgDuKKKKACiiigAooooAKKKKACiiigAooooAKKKKACvP8A4Jf8kh0L/t4/9KJK9Arh/hA9nJ8LdGawgngtT5+yOeYSuv7+TOWCqDzn+EenPWgDuKKKKACiiigAooooAKKKKACiiigAooooAKKr7Lz+0d/nwfYfKx5PknzPMz97fuxtxxt25zzu7UUAcP8ACz/mdf8Asa77/wBkr0CvP/hZ/wAzr/2Nd9/7JXoFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRR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ACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigDz/wD5uF/7lT/27oo/5uF/7lT/ANu6KAD4Wf8AM6/9jXff+yV6BXn/AMLP+Z1/7Gu+/wDZK9AoAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKAPP/wDm4X/uVP8A27oo/wCbhf8AuVP/AG7ooAPhZ/zOv/Y133/slegV5/8ACz/mdf8Asa77/wBkr0CgAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooA8//wCbhf8AuVP/AG7oo/5uF/7lT/27ooAPhZ/zOv8A2Nd9/wCyV6BXn/ws/wCZ1/7Gu+/9kr0CgAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooA8/wD+bhf+5U/9u6KP+bhf+5U/9u6KAD4Wf8zr/wBjXff+yV6BXn/ws/5nX/sa77/2SvQKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigDz/wD5uF/7lT/27oo/5uF/7lT/ANu6KAD4Wf8AM6/9jXff+yV6BXn/AMLP+Z1/7Gu+/wDZK9AoAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKAPP/wDm4X/uVP8A27oo/wCbhf8AuVP/AG7ooAPhZ/zOv/Y133/slegV5/8ACz/mdf8Asa77/wBkr0CgAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooA8//wCbhf8AuVP/AG7oo/5uF/7lT/27ooAPhZ/zOv8A2Nd9/wCyV6BXgfh3x1rXg+41+3uNB0Z577WLi+kjk8U2UTQM5AMRBJOVK4zx9BW5/wALo1T/AKFvQ/8AwsLKgD2CivH/APhdGqf9C3of/hYWVH/C6NU/6FvQ/wDwsLKgD2CivH/+F0ap/wBC3of/AIWFlR/wujVP+hb0P/wsLKgD2CivH/8AhdGqf9C3of8A4WFlR/wujVP+hb0P/wALCyoA9gorx/8A4XRqn/Qt6H/4WFlR/wALo1T/AKFvQ/8AwsLKgD2CivH/APhdGqf9C3of/hYWVH/C6NU/6FvQ/wDwsLKgD2CivH/+F0ap/wBC3of/AIWFlR/wujVP+hb0P/wsLKgD2CivH/8AhdGqf9C3of8A4WFlR/wujVP+hb0P/wALCyoA9gorx/8A4XRqn/Qt6H/4WFlR/wALo1T/AKFvQ/8AwsLKgD2CivH/APhdGqf9C3of/hYWVH/C6NU/6FvQ/wDwsLKgD2CivH/+F0ap/wBC3of/AIWFlR/wujVP+hb0P/wsLKgD2CivH/8AhdGqf9C3of8A4WFlR/wujVP+hb0P/wALCyoA9gorx/8A4XRqn/Qt6H/4WFlR/wALo1T/AKFvQ/8AwsLKgD2CivH/APhdGqf9C3of/hYWVH/C6NU/6FvQ/wDw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/wBT745/8HH/ANhXoFFAHn//AAqz/qffHP8A4OP/ALCj/hVn/U++Of8Awcf/AGFegUUAef8A/CrP+p98c/8Ag4/+wo/4VZ/1Pvjn/wAHH/2FegUUAef/APCrP+p98c/+Dj/7Cj/hVn/U++Of/Bx/9hXoFFAHk/hvQP8AhHPjrJZ/2vquqb/DRl87U7nz5FzcgbQ2BheM49SfWitj/m4X/uVP/buigA+Fn/M6/wDY133/ALJXoFef/Cz/AJnX/sa77/2SvQKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigDz/AP5uF/7lT/27oo/5uF/7lT/27ooAPhZ/zOv/AGNd9/7JXoFef/Cz/mdf+xrvv/ZK9AoAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKAPP/APm4X/uVP/buij/m4X/uVP8A27ooAPhZ/wAzr/2Nd9/7JXoFef8Aws/5nX/sa77/ANkr0CgAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooA8//AObhf+5U/wDbuij/AJuF/wC5U/8AbuigA+Fn/M6/9jXff+yV6BXn/wALP+Z1/wCxrvv/AGSvQKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigDz//AJuF/wC5U/8Abuij/m4X/uVP/buigA+Fn/M6/wDY133/ALJXoFef/Cz/AJnX/sa77/2SvQKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigDz/AP5uF/7lT/27oo/5uF/7lT/27ooAPhZ/zOv/AGNd9/7JXoFef/Cz/mdf+xrvv/ZK9AoAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKAPP/APm4X/uVP/buij/m4X/uVP8A27ooA848F/Gvw34c/wCEh+2WWqv/AGlrdzqEPkxRnbHJt2hsyDDcHIGR710//DR3g/8A6Buuf9+If/jtFFAB/wANHeD/APoG65/34h/+O0f8NHeD/wDoG65/34h/+O0UUAH/AA0d4P8A+gbrn/fiH/47R/w0d4P/AOgbrn/fiH/47RRQAf8ADR3g/wD6Buuf9+If/jtH/DR3g/8A6Buuf9+If/jtFFAB/wANHeD/APoG65/34h/+O0f8NHeD/wDoG65/34h/+O0UUAH/AA0d4P8A+gbrn/fiH/47R/w0d4P/AOgbrn/fiH/47RRQAf8ADR3g/wD6Buuf9+If/jtH/DR3g/8A6Buuf9+If/jtFFAB/wANHeD/APoG65/34h/+O0f8NHeD/wDoG65/34h/+O0UUAH/AA0d4P8A+gbrn/fiH/47R/w0d4P/AOgbrn/fiH/47RRQAf8ADR3g/wD6Buuf9+If/jtH/DR3g/8A6Buuf9+If/jtFFAB/wANHeD/APoG65/34h/+O0f8NHeD/wDoG65/34h/+O0UUAH/AA0d4P8A+gbrn/fiH/47R/w0d4P/AOgbrn/fiH/47RRQAf8ADR3g/wD6Buuf9+If/jtH/DR3g/8A6Buuf9+If/jtFFAB/wANHeD/APoG65/34h/+O0f8NHeD/wDoG65/34h/+O0UUAH/AA0d4P8A+gbrn/fiH/47R/w0d4P/AOgbrn/fiH/47RRQAf8ADR3g/wD6Buuf9+If/jtH/DR3g/8A6Buuf9+If/jtFFAB/wANHeD/APoG65/34h/+O0f8NHeD/wDoG65/34h/+O0UUAH/AA0d4P8A+gbrn/fiH/47R/w0d4P/AOgbrn/fiH/47RRQAf8ADR3g/wD6Buuf9+If/jtH/DR3g/8A6Buuf9+If/jtFFAB/wANHeD/APoG65/34h/+O0f8NHeD/wDoG65/34h/+O0UUAH/AA0d4P8A+gbrn/fiH/47R/w0d4P/AOgbrn/fiH/47RRQAf8ADR3g/wD6Buuf9+If/jtH/DR3g/8A6Buuf9+If/jtFFAB/wANHeD/APoG65/34h/+O0f8NHeD/wDoG65/34h/+O0UUAH/AA0d4P8A+gbrn/fiH/47R/w0d4P/AOgbrn/fiH/47RRQAf8ADR3g/wD6Buuf9+If/jtH/DR3g/8A6Buuf9+If/jtFFAB/wANHeD/APoG65/34h/+O0f8NHeD/wDoG65/34h/+O0UUAH/AA0d4P8A+gbrn/fiH/47R/w0d4P/AOgbrn/fiH/47RRQAf8ADR3g/wD6Buuf9+If/jtH/DR3g/8A6Buuf9+If/jtFFAB/wANHeD/APoG65/34h/+O0f8NHeD/wDoG65/34h/+O0UUAH/AA0d4P8A+gbrn/fiH/47R/w0d4P/AOgbrn/fiH/47RRQAf8ADR3g/wD6Buuf9+If/jtH/DR3g/8A6Buuf9+If/jtFFAB/wANHeD/APoG65/34h/+O0f8NHeD/wDoG65/34h/+O0UUAH/AA0d4P8A+gbrn/fiH/47R/w0d4P/AOgbrn/fiH/47RRQAf8ADR3g/wD6Buuf9+If/jtH/DR3g/8A6Buuf9+If/jtFFAB/wANHeD/APoG65/34h/+O0f8NHeD/wDoG65/34h/+O0UUAH/AA0d4P8A+gbrn/fiH/47R/w0d4P/AOgbrn/fiH/47RRQAf8ADR3g/wD6Buuf9+If/jtH/DR3g/8A6Buuf9+If/jtFFAB/wANHeD/APoG65/34h/+O0f8NHeD/wDoG65/34h/+O0UUAZ/hb4j6P4u+O9peWFtfRx3WiPp6CdEBEiyNOScMfl2qRnrntjmiiigD//Z',
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
        'barcode_config' => array(
            'width_factor' => 1,
            'height'       => 50,
        ),
    );
    
    protected $orders = array();
    
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
        $this->offset = isset($_GET['offset']) ? $_GET['offset'] : 0;
        if( $this->offset > ($this->per_page - 1) ){
            $this->offset = ($this->per_page - 1);
        }
        
        // definir imagens personalizadas
        if( is_array($this->config['images']) ){
            $this->images = wp_parse_args( $this->config['images'], $this->images );
        }
        
        // definir configurações da página do admin
        $this->admin_title        = $this->config['admin']['title'];
        $this->individual_buttons = $this->config['admin']['individual_buttons'];
        $this->layout_select      = $this->config['admin']['layout_select'];
        $this->print_invoice      = $this->config['admin']['print_invoice'];
        
        // definir configurações do código de barras
        $this->barcode_config = $this->config['barcode_config'];
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
            
            if( $this->print_invoice == 1 ){
                $this->print_invoices();
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
        
        // guardar o pedido em orders
        $this->orders[ $id ] = $order;
        
        $address = $this->get_address( $order );
        $address = $this->validate_address( $address );
        //pre($address);
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
        $address[] = "<span class='city-state'>{$store_info['woocommerce_store_city']} / {$store_info['state']} - Brasil</span>";
        
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


