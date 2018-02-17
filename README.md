
# Excs WooCommerce Print Orders

Plugin para impressão de etiquetas de destinatários, remetentes e declaração dos correios a partir de pedidos do WooCommerce.

## Hooks

### Editar configurações

Utilizar o hook `excs_print_orders_config`:
```php
add_filter( 'excs_print_orders_config', 'custom_print_orders_config' );
function custom_print_orders_config( $config ){
	// adicionar arquivo CSS
    $config['css'] = array(
        'file' => ABS_PATH .  '/css/print-orders.css',
    );
    return $config;
}
```

Defaults de `$config`:
```php
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
```
### Adicionar layouts

É preciso que um layout esteja registrado em `$layouts` para que possa ser utilizado. Utilizar o hook `excs_print_orders_layouts`.

Exemplo adicionando um novo grupo de layouts chamado "Custom", com dois layouts, "foo" e "bar":
```php
add_filter( 'excs_print_orders_layouts', 'custom_print_orders_layouts' );
function custom_print_orders_layouts( $layouts ){
    $layouts['custom'] = array(
        'name' => 'Custom',
        'items' => array(
            'foo' => array(
                'name'         => 'Foo',
                'paper'        => 'A4',
                'per_page'     => 6,
                'width'        => '90mm',
                'height'       => '80mm',
                'page_margins' => '15mm 10mm 0 15mm',
                'item_margin'  => '0 0 0 0',
            ),
            'bar' => array(
                'name'         => 'Bar',
                'paper'        => 'Letter',
                'per_page'     => 8,
                'width'        => '90mm',
                'height'       => '30mm',
                'page_margins' => '0 0 0 0',
                'item_margin'  => '0 0 0 0',
            ),
        ),
    );
    
    return $layouts;
}
```

Exemplo adicionando um novos itens no grupo pre-existente 'pimaco':
```php
add_filter( 'excs_print_orders_layouts', 'custom_print_orders_layouts' );
function custom_print_orders_layouts( $layouts ){
    
    $layouts['pimaco']['items']['6082'] = array(
        'name'         => '6082',
        'paper'        => 'Letter',
        'per_page'     => 14,
        'page_margins' => '19mm 0 0 6mm',
        'width'        => '101.6mm',
        'height'       => '33.9mm',
        'item_margin'  => '0',
    );
    $layouts['pimaco']['items']['8099F'] = array(
        'name'         => '8099F',
        'paper'        => 'Letter',
        'per_page'     => 10,
        'width'        => '77.79mm',
        'height'       => '46.56mm',
        'page_margins' => '19mm 19mm 0 19mm',
        'item_margin'  => '0 83px 0 0',
    );
    
    return $layouts;
}
```
----------
### Escolher o layout

Para escolher um layout dentre os layouts disponíveis, é preciso definir o grupo e item respectivo:
```php
add_filter( 'excs_print_orders_config', 'custom_print_orders_config' );
function custom_print_orders_config( $config ){
	// definir o grupo e item de layout
    $config['layout'] = array(
        'group' => 'pimaco',
        'item'  => '6183',
    );
    return $config;
}
```

### Layouts default
```php
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
        ),
    ),
);
```