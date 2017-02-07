<?php
/**
 * Order Migration trial version, using Prestashop API
 * Developed by Nethues Team for Order migration purpose
 * From Old OSCommerce Website to New Prestashop
 * NOTE: various PrestaShop's inbuilt import features were used 
 * (customer, customer's address and ZONE based settings manually to match osCommerce Zone settings)
 * 
 * NOTE: This file is outdated please use order_new.php for proper migration
 */
require 'config.php';
if (USE_API) {
    $filename = "lastSourceOrderID.log";
    $lastSourceOrderID = (int) file_get_contents($filename);
    $query = "SELECT o.*,c.customers_default_address_id,CASE WHEN os.shipping_type='flat_flat' THEN '1' ELSE '2' END as carrier "
            . "FROM " . DB_TABLE_PREFIX_1 . "orders o "
            . "INNER JOIN " . DB_TABLE_PREFIX_1 . "customers c ON(o.customers_id=c.customers_id) "
            . "INNER JOIN " . DB_TABLE_PREFIX_1 . "orders_shipping os ON (o.orders_id=os.orders_id) "
            . "WHERE orders_id > " . (int) $lastSourceOrderID . " ORDER BY orders_id ASC LIMIT 10";
    $result = $dbSource->query($query);
    if ($result) {
        while ($row = $result->fetch_object()) {
            try {
                // creating webservice access
                $webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
                $xmlCarts = $webService->get(array('url' => PS_SHOP_PATH . '/api/carts?schema=blank'));
                $xmlCarts->cart->id_customer = $row->customers_id;
                $xmlCarts->cart->id_address_delivery = $row->customers_default_address_id;
                $xmlCarts->cart->id_address_invoice = $row->customers_default_address_id;
                $xmlCarts->cart->id_currency = 1;
                $xmlCarts->cart->id_lang = 1;
                $xmlCarts->cart->id_carrier = $row->carrier;
                $opQuery = "SELECT op.* FROM " . DB_TABLE_PREFIX_1 . "orders_products op"
                        . "LEFT JOIN orders_products_attributes opa ON(op.orders_id=opa.orders_id AND op.products_id=opa.orders_products_id)"
                        . "WHERE op.orders_id=" . (int) $row->orders_id . " "
                        . "ORDER BY op.orders_products_id ASC";
                $resultOP = $dbSource->query($opQuery);
                while ($rowOP = $resultOP->fetch_object()) {
                    $xmlCarts->cart->associations->cart_rows->cart_row->id_product = $rowOP->products_id;
                    $xmlCarts->cart->associations->cart_rows->cart_row->quantity = $rowOP->products_quantity;
                    $xmlCarts->cart->associations->cart_rows->cart_row->id_product_attribute = $rowOP->orders_products_attributes_id;
                }
                $optCart = array('resource' => 'carts');
                $optCart['postXml'] = $xmlCarts->asXML();
                $xmlCart = $webService->add($optCart);
                $cartID = $xmlCart->cart->id;
                if (!empty($cartID)) {
                    // CREATE Order
                    $xmlOrders = $webService->get(array('url' => PS_SHOP_PATH . '/api/orders?schema=blank'));
                    $xmlOrders->order->id_shop_group = 1;
                    $xmlOrders->order->id_shop = 1;
                    //$xmlOrders->order->secure_key = '89a28e20bbe36f1f4e7d01ee9c88e48e';
                    $xmlOrders->order->id_carrier = $row->carrier;
                    $xmlOrders->order->id_lang = 1;
                    $xmlOrders->order->id_customer = $row->customers_id;
                    $xmlOrders->order->id_cart = $cartID;
                    $xmlOrders->order->id_currency = 1;
                    $xmlOrders->order->id_address_delivery = $row->customers_default_address_id;
                    $xmlOrders->order->id_address_invoice = $row->customers_default_address_id;
                    $xmlOrders->order->current_state = 10;
                    $xmlOrders->order->payment = 'Payment by check';
                    $xmlOrders->order->conversion_rate = '1';
                    $xmlOrders->order->module = 'cheque';
                    $xmlOrders->order->total_discounts = 0.00;
                    $xmlOrders->order->total_discounts_tax_incl = 0.00;
                    $xmlOrders->order->total_discounts_tax_excl = 0.00;

                    $xmlOrders->order->total_paid = 16.51;
                    $xmlOrders->order->total_paid_tax_incl = 16.51;
                    $xmlOrders->order->total_paid_tax_excl = 16.51;
                    $xmlOrders->order->total_paid_real = '0';
                    $xmlOrders->order->total_products = 16.51;
                    $xmlOrders->order->total_products_wt = 16.51;
                    $xmlOrders->order->total_shipping = 0.00;
                    $xmlOrders->order->total_shipping_tax_incl = 0.00;
                    $xmlOrders->order->total_shipping_tax_excl = 0.00;
                    $xmlOrders->order->total_wrapping = 0.00;
                    $xmlOrders->order->total_wrapping_tax_incl = 0.00;
                    $xmlOrders->order->total_wrapping_tax_excl = 0.00;
                    $xmlOrders->order->round_mode = 2;
                    $xmlOrders->order->invoice_date = date("Y-m-d H:i:s");
                    $xmlOrders->order->delivery_date = date("Y-m-d H:i:s");
                    $xmlOrders->order->date_add = date("Y-m-d H:i:s");
                    $xmlOrders->order->date_upd = date("Y-m-d H:i:s");
                    $xmlOrders->order->valid = 0;

                    //Association
                    $xmlOrders->order->associations->order_rows->order_row[0]->product_id = 1;
                    $xmlOrders->order->associations->order_rows->order_row[0]->product_attribute_id = 3;
                    $xmlOrders->order->associations->order_rows->order_row[0]->product_quantity = 1;
                    $xmlOrders->order->associations->order_rows->order_row[0]->product_name = 'Faded Short Sleeves T-shirt - Size : M, Color : Orange';
                    $xmlOrders->order->associations->order_rows->order_row[0]->product_reference = 'demo_1';
                    $xmlOrders->order->associations->order_rows->order_row[0]->product_ean13 = 0;
                    $xmlOrders->order->associations->order_rows->order_row[0]->product_upc = 0;
                    $xmlOrders->order->associations->order_rows->order_row[0]->product_price = 16.51;
                    $xmlOrders->order->associations->order_rows->order_row[0]->unit_price_tax_incl = 16.51;
                    $xmlOrders->order->associations->order_rows->order_row[0]->unit_price_tax_excl = 16.51;


                    $optOrders = array('resource' => 'orders');
                    $optOrders['postXml'] = $xmlOrders->asXML();
                    $xmlOrder = $webService->add($optOrders);
                    //die('<pre>' . print_r($xmlOrder, 1) . '</pre>');
                    $OrderID = $xmlOrder->order->id;
                    $Order_secure_key = $xmlOrder->order->secure_key;

                    $xmlHistories = $webService->get(array('url' => PS_SHOP_PATH . '/api/order_histories?schema=blank'));

                    $xmlHistories->order_history->id_order = $OrderID;
                    $xmlHistories->order_history->id_order_state = '3';

                    $optOrderHistory = array('resource' => 'order_histories');
                    $optOrderHistory['postXml'] = $xmlHistories->asXML();
                    $xmlHistory = $webService->add($optOrderHistory);
                    echo "Successfully added order. OrderID:" . $OrderID;
                }
            } catch (PrestaShopWebserviceException $ex) {
                // Shows a message related to the error
                echo 'Other error: <br />' . $ex->getMessage();
            }
        }
        mysqli_close($dbSource);
        if (!USE_API) {
            mysqli_close($dbTarget);
        }
    }
    /* close statement */
    $result->close();
}