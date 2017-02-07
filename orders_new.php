<?php

/**
 * Order Migration trial version, using Prestashop API
 * Developed by Nethues Team for Order migration purpose
 * From Old OSCommerce Website to New Prestashop
 * NOTE: it is RECOMMENDED to PRE-import customers and other required Items via Prestashop's inbuilt import features, 
 * various PrestaShop's inbuilt import features can be used (customer, customer's address and ZONE based settings manually 
 * to match osCommerce Zone settings)
 * NOTE: This file use NON_API version of migration and this script is recommended, for proper migration.
 * Categories, Products, Combinations, Customers, Addresses, Manufacturers, Suppliers Should be pre imported from Prestashop Admin
 * ia Advanced Parameters > CSV import page (Also recommended to Force all ID numbers=Yes)
 */
set_time_limit(0);
require 'config.php';
/**
 * A file to hold last osCommerce OrderID processed, The script is designed to run in chunks, 
 * it would be great if one can run it via command line interface, if for any reason you want to restart
 * Then delete lastSourceOrderID.log file and re-run again
 */
$filename = __DIR__ . "/lastSourceOrderID.log";
$lastSourceOrderID = (int) file_get_contents($filename);
$query = "SELECT o.*,(select os.shipping_type FROM orders_shipping os WHERE os.orders_id=o.orders_id LIMIT 1) AS shipping_type "
        . "FROM " . DB_TABLE_PREFIX_1 . "orders o "
        . "WHERE o.orders_id > " . (int) $lastSourceOrderID . " ORDER BY o.orders_id ASC LIMIT 10000";
$result = $dbSource->query($query);
if ($result) {
    //Get Prestashop's default values (e.g. CurrencyID, languageID)
    $id_currency = getDefaultValue("PS_CURRENCY_DEFAULT");
    $id_language = getDefaultValue("PS_LANG_DEFAULT");

    //Get Active Shop record
    $sqlShop = "SELECT * FROM `" . DB_TABLE_PREFIX_2 . "shop` WHERE active=1";
    $resShop = $dbTarget->query($sqlShop);
    if ($resShop && $resShop->num_rows > 0) {
        $rShop = $resShop->fetch_object();
    }
    /**
     * Looping through All OSCommenrce Orders
     */
    while ($row = $result->fetch_object()) {
        //$dbTarget->begin_transaction(); //Not working on Old version...
        WriteLastID($filename, $row->orders_id);
        $carrierid = (int) getCarrierId($row->shipping_type);
        $ArrCustName = explode(" ", $row->customers_name);
        $Customer_ID = $row->customers_id;

        //Get Other Details for customer from Target DB
        $sqlTCust = "SELECT * FROM `" . DB_TABLE_PREFIX_2 . "customer` WHERE lower(email)=lower('" . $dbTarget->real_escape_string(trim($row->customers_email_address)) . "')";
        $resTCust = $dbTarget->query($sqlTCust);
        if ($resTCust && $resTCust->num_rows == 0) {
            //Oops, Customer does not exists in the target DB. 
            //Attempt to Create a Customer Record for them.
            $arrayCustomerDetails = array(
                'id_shop_group' => (int) $rShop->id_shop_group,
                'id_shop' => (int) $rShop->id_shop,
                'id_gender' => 0,
                'id_default_group' => getCustomerGroupId(),
                'id_lang' => $id_language,
                'company' => $row->customers_company,
                'firstname' => $ArrCustName[0],
                'lastname' => $ArrCustName[1],
                'email' => $row->customers_email_address,
                'passwd' => 'd82113265a73f34abc33419666032759', //Password will be the same (for now)
                'last_passwd_gen' => date("Y-m-d"),
                'birthday' => '0000-00-00',
                'optin' => '1',
                //'website' => '',
                'outstanding_allow_amount' => '0.000000',
                //'show_public_prices' => '0',
                //'max_payment_days' => '0',
                'secure_key' => md5(uniqid(rand(), true)),
                //'note' => '',
                'active' => '0',
                'is_guest' => '0',
                'deleted' => '1',
                'date_add' => date("Y-m-d H:i:s", strtotime($row->date_purchased)),
                'date_upd' => date("Y-m-d H:i:s", strtotime($row->date_purchased))
            );
            $Customer_ID = insert($dbTarget, DB_TABLE_PREFIX_2 . 'customer', $arrayCustomerDetails);
            if (!$Customer_ID) {
                die("Oops, customer creation error...");
            }

            //Add Address Book for NEW customer
            //Need to insert address
            $arrayCustomerAddress = array(
                "id_country" => getCountryIdByName($row->customers_country),
                "id_state" => getStateIdByName($row->customers_state),
                "id_customer" => (int) $Customer_ID,
                "lastname" => $ArrCustName[1],
                "firstname" => $ArrCustName[0],
                "address1" => $row->customers_street_address,
                "address2" => $row->customers_suburb,
                "postcode" => $row->customers_postcode,
                "city" => $row->customers_city,
                "date_add" => date("Y-m-d H:i:s", strtotime($row->date_purchased)),
                "date_upd" => date("Y-m-d H:i:s", strtotime($row->date_purchased)),
                "active" => 1,
                "deleted" => 0,
            );
            $lastAddressID = insert($dbTarget, DB_TABLE_PREFIX_2 . 'address', $arrayCustomerAddress);

            //Get customer's Address records for further use
            $sqlAddress = "SELECT id_address FROM `" . DB_TABLE_PREFIX_2 . "address` WHERE id_address=" . (int) $lastAddressID . " AND id_customer=" . (int) $row->customers_id;
            $resAddress = $dbTarget->query($sqlAddress);
            if ($resAddress && $resAddress->num_rows > 0) {
                $rAddress = $resAddress->fetch_object();
            }
            $customers_address = $row->customers_street_address . $row->customers_suburb . $row->customers_postcode . $row->customers_city . $row->customers_state . $row->customers_country;
            $billing_address = $row->billing_street_address . $row->billing_suburb . $row->billing_postcode . $row->billing_city . $row->billing_state . $row->billing_country;
            $delivery_address = $row->delivery_street_address . $row->delivery_suburb . $row->delivery_postcode . $row->delivery_city . $row->delivery_state . $row->delivery_country;

            //If we don't have SAME billing address we will create one more address record.
            if ($customers_address <> $billing_address) {
                //Need to insert BILLING address
                $ArrBillName = explode(" ", $row->billing_name);
                $arrayBillAddress = array(
                    "id_country" => getCountryIdByName($row->billing_country),
                    "id_state" => getStateIdByName($row->billing_state),
                    "id_customer" => (int) $Customer_ID,
                    "lastname" => $ArrBillName[1],
                    "firstname" => $ArrBillName[0],
                    "address1" => $row->billing_street_address,
                    "address2" => $row->billing_suburb,
                    "postcode" => $row->billing_postcode,
                    "city" => $row->billing_city,
                    "date_add" => date("Y-m-d H:i:s", strtotime($row->date_purchased)),
                    "date_upd" => date("Y-m-d H:i:s", strtotime($row->date_purchased)),
                    "active" => 1,
                    "deleted" => 0,
                );
                $lastBillAddressID = insert($dbTarget, DB_TABLE_PREFIX_2 . 'address', $arrayBillAddress);

                //Get customer's Billing Address records for further use
                $sqlBillAddress = "SELECT id_address FROM `" . DB_TABLE_PREFIX_2 . "address` WHERE id_address=" . (int) $lastBillAddressID . " AND id_customer=" . (int) $row->customers_id;
                $resAddress = $dbTarget->query($sqlBillAddress);
                if ($resBillAddress && $sqlBillAddress->num_rows > 0) {
                    $rBillAddress = $resBillAddress->fetch_object();
                }
            }
            if ($customers_address <> $delivery_address) {
                //Need to insert SHIPPING address
                $ArrShipName = explode(" ", $row->delivery_name);
                $arrayShipAddress = array(
                    "id_country" => getCountryIdByName($row->delivery_country),
                    "id_state" => getStateIdByName($row->delivery_state),
                    "id_customer" => (int) $Customer_ID,
                    "lastname" => $ArrShipName[1],
                    "firstname" => $ArrShipName[0],
                    "address1" => $row->delivery_street_address,
                    "address2" => $row->delivery_suburb,
                    "postcode" => $row->delivery_postcode,
                    "city" => $row->delivery_city,
                    "date_add" => date("Y-m-d H:i:s", strtotime($row->date_purchased)),
                    "date_upd" => date("Y-m-d H:i:s", strtotime($row->date_purchased)),
                    "active" => 1,
                    "deleted" => 0,
                );
                $lastShipAddressID = insert($dbTarget, DB_TABLE_PREFIX_2 . 'address', $arrayShipAddress);

                //Get customer's Delivery/Shipping Address records for further use
                $sqlShipAddress = "SELECT id_address FROM `" . DB_TABLE_PREFIX_2 . "address` WHERE id_address=" . (int) $lastShipAddressID . " AND id_customer=" . (int) $row->customers_id;
                $resAddress = $dbTarget->query($sqlShipAddress);
                if ($resShipAddress && $sqlShipAddress->num_rows > 0) {
                    $rShipAddress = $resShipAddress->fetch_object();
                }
            }
            //End Add Address Book for new customer
            //Get Customer's Record
            $sqlTCust1 = "SELECT * FROM `" . DB_TABLE_PREFIX_2 . "customer` WHERE id_customer=" . (int) $Customer_ID;
            $resTCust = $dbTarget->query($sqlTCust1);
        }
        if ($resTCust && $resTCust->num_rows > 0) {
            //Great! We have a customer record...
            $rTCustomer = $resTCust->fetch_object();
            //Get AddressID for customer from Target DB
            $sqlTAddress = "SELECT id_address FROM `" . DB_TABLE_PREFIX_2 . "address` WHERE id_customer=" . (int) $Customer_ID;
            $resAddress = $dbTarget->query($sqlTAddress);
            if ($resAddress && $resAddress->num_rows > 0) {
                $rAddress = $resAddress->fetch_object();
            } else {
                //Oops, We do have customer record but we don't have their address
                //Need to insert address
                $arrayCustomerAddress = array(
                    "id_country" => getCountryIdByName($row->customers_country),
                    "id_state" => getStateIdByName($row->customers_state),
                    "id_customer" => (int) $Customer_ID,
                    "lastname" => $ArrCustName[1],
                    "firstname" => $ArrCustName[0],
                    "address1" => $row->customers_street_address,
                    "address2" => $row->customers_suburb,
                    "postcode" => $row->customers_postcode,
                    "city" => $row->customers_city,
                    "date_add" => date("Y-m-d H:i:s", strtotime($row->date_purchased)),
                    "date_upd" => date("Y-m-d H:i:s", strtotime($row->date_purchased)),
                    "active" => 1,
                    "deleted" => 0,
                );
                $lastAddressID = insert($dbTarget, DB_TABLE_PREFIX_2 . 'address', $arrayCustomerAddress);

                //Get customer's Address records for further use
                $sqlAddress = "SELECT id_address FROM `" . DB_TABLE_PREFIX_2 . "address` WHERE id_address=" . (int) $lastAddressID . " AND id_customer=" . (int) $row->customers_id;
                $resAddress = $dbTarget->query($sqlAddress);
                if ($resAddress && $resAddress->num_rows > 0) {
                    $rAddress = $resAddress->fetch_object();
                }
                $customers_address = $row->customers_street_address . $row->customers_suburb . $row->customers_postcode . $row->customers_city . $row->customers_state . $row->customers_country;
                $billing_address = $row->billing_street_address . $row->billing_suburb . $row->billing_postcode . $row->billing_city . $row->billing_state . $row->billing_country;
                $delivery_address = $row->delivery_street_address . $row->delivery_suburb . $row->delivery_postcode . $row->delivery_city . $row->delivery_state . $row->delivery_country;

                //If we don't have SAME billing address we will create one more address record.
                if ($customers_address <> $billing_address) {
                    //Need to insert BILLING address
                    $ArrBillName = explode(" ", $row->billing_name);
                    $arrayBillAddress = array(
                        "id_country" => getCountryIdByName($row->billing_country),
                        "id_state" => getStateIdByName($row->billing_state),
                        "id_customer" => (int) $Customer_ID,
                        "lastname" => $ArrBillName[1],
                        "firstname" => $ArrBillName[0],
                        "address1" => $row->billing_street_address,
                        "address2" => $row->billing_suburb,
                        "postcode" => $row->billing_postcode,
                        "city" => $row->billing_city,
                        "date_add" => date("Y-m-d H:i:s", strtotime($row->date_purchased)),
                        "date_upd" => date("Y-m-d H:i:s", strtotime($row->date_purchased)),
                        "active" => 1,
                        "deleted" => 0,
                    );
                    $lastBillAddressID = insert($dbTarget, DB_TABLE_PREFIX_2 . 'address', $arrayBillAddress);

                    //Get customer's Billing Address records for further use
                    $sqlBillAddress = "SELECT id_address FROM `" . DB_TABLE_PREFIX_2 . "address` WHERE id_address=" . (int) $lastBillAddressID . " AND id_customer=" . (int) $row->customers_id;
                    $resAddress = $dbTarget->query($sqlBillAddress);
                    if ($resBillAddress && $sqlBillAddress->num_rows > 0) {
                        $rBillAddress = $resBillAddress->fetch_object();
                    }
                }
                if ($customers_address <> $delivery_address) {
                    //Need to insert SHIPPING address
                    $ArrShipName = explode(" ", $row->delivery_name);
                    $arrayShipAddress = array(
                        "id_country" => getCountryIdByName($row->delivery_country),
                        "id_state" => getStateIdByName($row->delivery_state),
                        "id_customer" => (int) $Customer_ID,
                        "lastname" => $ArrShipName[1],
                        "firstname" => $ArrShipName[0],
                        "address1" => $row->delivery_street_address,
                        "address2" => $row->delivery_suburb,
                        "postcode" => $row->delivery_postcode,
                        "city" => $row->delivery_city,
                        "date_add" => date("Y-m-d H:i:s", strtotime($row->date_purchased)),
                        "date_upd" => date("Y-m-d H:i:s", strtotime($row->date_purchased)),
                        "active" => 1,
                        "deleted" => 0,
                    );
                    $lastShipAddressID = insert($dbTarget, DB_TABLE_PREFIX_2 . 'address', $arrayShipAddress);

                    //Get customer's Delivery/Shipping Address records for further use
                    $sqlShipAddress = "SELECT id_address FROM `" . DB_TABLE_PREFIX_2 . "address` WHERE id_address=" . (int) $lastShipAddressID . " AND id_customer=" . (int) $row->customers_id;
                    $resAddress = $dbTarget->query($sqlShipAddress);
                    if ($resShipAddress && $sqlShipAddress->num_rows > 0) {
                        $rShipAddress = $resShipAddress->fetch_object();
                    }
                }
                //End Add Address Book for existing customer                
            }

            //All good lets Create a New Cart for this ORDER
            $inCart = "INSERT INTO `" . DB_TABLE_PREFIX_2 . "cart` SET "
                    . "`id_shop_group`=$rShop->id_shop_group, "
                    . "`id_shop`=$rShop->id_shop, "
                    . "`id_carrier`=" . (int) $carrierid . ", "
                    . "`delivery_option`='', "
                    . "`id_lang`=" . (int) $id_language . ", "
                    . "`id_address_delivery`=" . (int) (isset($rShipAddress->id_address) ? $rShipAddress->id_address : $rAddress->id_address) . ", "
                    . "`id_address_invoice`=" . (int) (isset($rBillAddress->id_address) ? $rBillAddress->id_address : $rAddress->id_address) . ", "
                    . "`id_currency`=" . (int) $id_currency . ", "//1=DOLLER 2=POUND
                    . "`id_customer`=" . (int) $Customer_ID . ", "
                    . "`secure_key`='" . $rTCustomer->secure_key . "', "
                    . "`date_add`='" . date("Y-m-d H:i:s", strtotime($row->date_purchased)) . "', "
                    . "`date_upd`='" . date("Y-m-d H:i:s", strtotime($row->date_purchased)) . "'"
                    . "";
            LogMessage($inCart);// Log the insert (optional)
            $resCart = $dbTarget->query($inCart);
            if (!$resCart) {
                //Oops, some servers contains old versions do not support this, 
                //it is recommended to un-comment, in case below function works on the server.
                //$dbTarget->rollback(); 
                continue;
            }

            //We are good so far lets grab the cart ID
            $cartID = $dbTarget->insert_id;
            //Get Products details within order from Source DB (NOTE: this program assumes that we already have same product with SAME productID
            //Use PrestaShop Admin to import Categories, Products, Combinations, Customers, Addresses, Manufacturers, Suppliers 
            //via Advanced Parameters > CSV import page (Also recommended to Force all ID numbers=Yes) 
            $sqlSproducts = "SELECT * FROM `" . DB_TABLE_PREFIX_1 . "orders_products` WHERE orders_id=" . (int) $row->orders_id;
            $resProducts = $dbSource->query($sqlSproducts);
            if (!empty($cartID)) {
                while ($rCartProd = $resProducts->fetch_object()) {
                    $sqlSProdAttribute = "SELECT opa.products_options, "
                            . "opa.products_options_values "
                            . "FROM `" . DB_TABLE_PREFIX_1 . "orders_products_attributes` opa "
                            . "INNER JOIN `" . DB_TABLE_PREFIX_1 . "orders_products` op "
                            . "ON(opa.orders_id = op.orders_id AND opa.orders_products_id = op.orders_products_id) "
                            . "WHERE  op.orders_id=" . (int) $rCartProd->orders_id . " AND "
                            . "op.products_id=" . (int) $rCartProd->products_id;
                    LogMessage($sqlSProdAttribute);
                    $resProdAttrib = $dbSource->query($sqlSProdAttribute);
                    if ($resProdAttrib) {
                        if ($resProdAttrib->num_rows > 0) {
                            $rProdAttrib = $resProdAttrib->fetch_object();
                            //Get Attribute (IF available) on Target DB (import "Combinations"... based on source oscommerce product attribute)
                            $sqlTProdAttribCheck = "SELECT a.*, "
                                    . "agl.name as attr_groupname, "
                                    . "al.name as attribute_name, "
                                    . "pa.id_product_attribute "
                                    . "FROM `" . DB_TABLE_PREFIX_2 . "attribute` a "
                                    . "INNER JOIN " . DB_TABLE_PREFIX_2 . "attribute_lang al ON(a.id_attribute=al.id_attribute AND al.id_lang=".(int) $id_language.") "
                                    . "INNER JOIN " . DB_TABLE_PREFIX_2 . "attribute_group ag ON(ag.id_attribute_group=a.id_attribute_group) "
                                    . "INNER JOIN " . DB_TABLE_PREFIX_2 . "attribute_group_lang agl "
                                    . "ON(agl.id_attribute_group=a.id_attribute_group AND agl.id_lang=".(int) $id_language.") "
                                    . "INNER JOIN " . DB_TABLE_PREFIX_2 . "product_attribute_combination pac ON(pac.id_attribute=a.id_attribute) "
                                    . "INNER JOIN " . DB_TABLE_PREFIX_2 . "product_attribute pa ON(pa.id_product_attribute=pac.id_product_attribute) "
                                    . "WHERE pa.id_product=" . (int) $rCartProd->products_id . " AND "
                                    . "agl.name LIKE '%" . $dbTarget->real_Escape_string(trim($rProdAttrib->products_options)) . "%' AND "
                                    . "al.name LIKE '%" . $dbTarget->real_Escape_string(trim($rProdAttrib->products_options_values)) . "%' "
                                    . "GROUP BY a.id_attribute LIMIT 1";
                            LogMessage("\n======================\n" . $sqlTProdAttribCheck . "\n======================\n");//Log Query (optional)
                            $resTProdAttrib = $dbTarget->query($sqlTProdAttribCheck);
                            $bAttribAvail = ($resTProdAttrib && $resTProdAttrib->num_rows > 0);
                            if ($bAttribAvail) {
                                //Get the product attribute ID
                                $rAttrib = $resTProdAttrib->fetch_object();
                                $product_attribute = $rAttrib->id_product_attribute;
                            }
                        }
                    } else {
                        $error = "Oops, product attribute record not found for productid: " . (int) $rSOp->products_id;
                        WriteErrorLog($error);
                    }
                    
                    //Okay, lets add all the order products into CART into Prestashop DB
                    $inCartProd = "INSERT INTO `" . DB_TABLE_PREFIX_2 . "cart_product` "
                            . "(`id_cart`, `id_product`, `id_address_delivery`, `id_shop`, "
                            . "`id_product_attribute`, `quantity`, `date_add`) "
                            . "VALUES "
                            . "(" . (int) $cartID . ", " . (int) $rCartProd->products_id . ", " . (int) (isset($rShipAddress->id_address) ? $rShipAddress->id_address : $rAddress->id_address) . ", $rShop->id_shop, "
                            . "" . ($resTProdAttrib && $resTProdAttrib->num_rows > 0 ? $product_attribute : 0) . ", " . (int) $rCartProd->products_quantity . ",'" . date("Y-m-d H:i:s", strtotime($row->date_purchased)) . "');";
                    LogMessage($inCartProd);
                    if (!$dbTarget->query($inCartProd)) {
                        //Oops, some servers contains old versions do not support this, 
                        //it is recommended to un-comment, in case below function works on the server.                        
                        //$dbTarget->rollback();
                        continue;
                    }
                }

                //Get discount if any (From Souce DB aka osCommerce DB)
                $sqlCheckOrderDetails = "SELECT * FROM `" . DB_TABLE_PREFIX_1 . "orders_total` WHERE orders_id=" . (int) $row->orders_id;
                $resOrderDiscount = $dbSource->query($sqlCheckOrderDetails);
                $order_subtotal = $orders_discount = $orders_shipping = $orders_shipping_weight = $order_total = 0.00;
                if ($resOrderDiscount) {
                    while ($rOrderTotal = $resOrderDiscount->fetch_object()) {
                        if ($rOrderTotal->class == 'ot_subtotal') {
                            $order_subtotal = $rOrderTotal->value;
                        }
                        if (stristr($rOrderTotal->class, 'discount')) {
                            $orders_discount = $rOrderTotal->value;
                        }
                        if ($rOrderTotal->class == 'ot_shipping') {
                            $orders_shipping = $rOrderTotal->value;
                            $orders_shipping_weight = preg_replace('/Total Shipping Weight =\s+(\d+)kg/', '$1', $rOrderTotal->title);
                        }
                        if ($rOrderTotal->class == 'ot_total') {
                            $order_total = $rOrderTotal->value;
                        }
                    }
                }
                //Get the Matching Order status (You might need to add macthing status you can insert some records into order_state, and order_state_lang 
                //tables on Prestashop DB,
                //TODO: Planning to simplyfy this for future.
                $current_state = getMatchingOrderStatus($row->orders_status, $row->payment_method);
                
                //Get Matching Payment Module For Prestashop
                $module = $dbTarget->real_escape_string(getModuleName($row->payment_method));
                $reference = generateReference();
                $insOrder = "INSERT INTO `" . DB_TABLE_PREFIX_2 . "orders` SET "
                        . "id_order=" . (int) $row->orders_id . ","
                        . "`reference`='$reference', "
                        . "`id_shop_group`='$rShop->id_shop_group', "
                        . "`id_shop`='$rShop->id_shop', "
                        . "`id_carrier`='" . $carrierid . "', "
                        . "`id_lang`=" . (int) $id_language . ", "
                        . "`id_customer`='" . $Customer_ID . "',"
                        . "`id_cart`='$cartID',"
                        . "`id_currency`=" . (int) $id_currency . ","//1=USD, 2=GBP
                        . "`id_address_delivery`='" . (isset($rShipAddress->id_address) ? $rShipAddress->id_address : $rAddress->id_address) . "',"
                        . "`id_address_invoice`='" . (isset($rBillAddress->id_address) ? $rBillAddress->id_address : $rAddress->id_address) . "',"
                        . "`current_state`='$current_state',"
                        . "`secure_key`= '" . $rTCustomer->secure_key . "',"
                        . "`payment`='" . $module . "',"
                        . "`conversion_rate`='1.000000',"
                        . "`module`='$module', "
                        . "`recyclable`='0', "
                        . "`total_discounts`='$orders_discount', "
                        . "`total_discounts_tax_incl`='$orders_discount', "
                        . "`total_discounts_tax_excl`='$orders_discount', "
                        . "`total_paid`='$order_total', "
                        . "`total_paid_tax_incl`='$order_total', "
                        . "`total_paid_tax_excl`='$order_total', "
                        . "`total_paid_real`='0.000000', "
                        . "`total_products`='$order_subtotal', "
                        . "`total_products_wt`='$order_subtotal', "
                        . "`total_shipping`='$orders_shipping', "
                        . "`total_shipping_tax_incl`='$orders_shipping', "
                        . "`total_shipping_tax_excl`='$orders_shipping', "
                        // . "`carrier_tax_rate`='0.000', "
                        // . "`total_wrapping`='0.000000', "
                        // . "`total_wrapping_tax_incl`='0.000000', "
                        // . "`total_wrapping_tax_excl`= '0.000000',  "
                        . "`round_mode`='2', "
                        . "`round_type`='2', "
                        //. "`invoice_number`='0', "
                        //. "`delivery_number`='0', "
                        . "`invoice_date`='" . date("Y-m-d H:i:s", strtotime($row->date_purchased)) . "', "
                        . "`delivery_date`='" . date("Y-m-d H:i:s", strtotime($row->date_purchased)) . "', "
                        . "`valid`='1', "
                        . "`date_add`= '" . date("Y-m-d H:i:s", strtotime($row->date_purchased)) . "',"
                        . "`date_upd`= NOW()";
                LogMessage($insOrder);
                if (!$dbTarget->query($insOrder)) {
                    //$dbTarget->rollback();
                    continue;
                }
                $OrderID = $dbTarget->insert_id;
                $sqlSourceOrderproducts = "SELECT * FROM `" . DB_TABLE_PREFIX_1 . "orders_products` WHERE orders_id=" . (int) $row->orders_id;
                $resOrderProducts = $dbSource->query($sqlSourceOrderproducts);
                if ($resOrderProducts) {
                    while ($rSOp = $resOrderProducts->fetch_object()) {
                        $product_attribute = 0;
                        $sqlSProdAttribute = "SELECT opa.products_options,opa.products_options_values FROM `" . DB_TABLE_PREFIX_1 . "orders_products_attributes` opa "
                                . "INNER JOIN `" . DB_TABLE_PREFIX_1 . "orders_products` op ON(opa.orders_id = op.orders_id AND opa.orders_products_id = op.orders_products_id) WHERE  op.orders_id=" . (int) $rSOp->orders_id . " AND op.products_id=" . (int) $rSOp->products_id;
                        LogMessage($sqlSProdAttribute);
                        $resProdAttrib = $dbSource->query($sqlSProdAttribute);
                        if ($resProdAttrib) {
                            if ($resProdAttrib->num_rows > 0) {
                                $rProdAttrib = $resProdAttrib->fetch_object();
                                //Check Attribute on Target DB
                                $sqlTProdAttribCheck = "
                                SELECT 
                                    a.*, agl.name as attr_groupname, al.name as attribute_name, pa.id_product_attribute
                                FROM `" . DB_TABLE_PREFIX_2 . "attribute` a
                                    INNER JOIN " . DB_TABLE_PREFIX_2 . "attribute_lang al ON(a.id_attribute=al.id_attribute AND al.id_lang=".(int) $id_language.")
                                    INNER JOIN " . DB_TABLE_PREFIX_2 . "attribute_group ag ON(ag.id_attribute_group=a.id_attribute_group)
                                    INNER JOIN " . DB_TABLE_PREFIX_2 . "attribute_group_lang agl ON(agl.id_attribute_group=a.id_attribute_group AND agl.id_lang=".(int) $id_language.")
                                    INNER JOIN " . DB_TABLE_PREFIX_2 . "product_attribute_combination pac ON(pac.id_attribute=a.id_attribute)
                                    INNER JOIN " . DB_TABLE_PREFIX_2 . "product_attribute pa ON(pa.id_product_attribute=pac.id_product_attribute)
                                WHERE 
                                    pa.id_product=" . (int) $rSOp->products_id . " AND agl.name LIKE '%" . $dbTarget->real_Escape_string(trim($rProdAttrib->products_options)) . "%' AND al.name LIKE '%" . $dbTarget->real_Escape_string(trim($rProdAttrib->products_options_values)) . "%'
                                GROUP BY 
                                    a.id_attribute
                                LIMIT 1";
                                LogMessage("\n======================\n" . $sqlTProdAttribCheck . "\n======================\n");
                                $resTProdAttrib = $dbTarget->query($sqlTProdAttribCheck);
                                $bAttribAvail = ($resTProdAttrib && $resTProdAttrib->num_rows > 0);
                                if ($bAttribAvail) {
                                    $rAttrib = $resTProdAttrib->fetch_object();
                                    $product_attribute = $rAttrib->id_product_attribute;
                                }
                            }
                        } else {
                            $error = "Oops, product attribute record not found for productid: " . (int) $rSOp->products_id;
                            WriteErrorLog($error);
                        }
                        $PRODUCT_NAME = $dbTarget->real_escape_string($rSOp->products_name);
                        if ($product_attribute > 0) {
                            $PRODUCT_NAME .= $dbTarget->real_escape_string(" - $rAttrib->attr_groupname: $rAttrib->attribute_name");
                        }
                        $sqlTProd = "SELECT * FROM `" . DB_TABLE_PREFIX_2 . "product` WHERE id_product=" . (int) $rSOp->products_id;
                        LogMessage("\n%%%%%%%%%%%%%%%" . $sqlTProd . "%%%%%%%%%%%%%%%%%%%%%\n");
                        $resTargetProduct = $dbTarget->query($sqlTProd);
                        if ($resTargetProduct && $resTargetProduct->num_rows > 0) {
                            $rTProd = $resTargetProduct->fetch_object();
                        }

                        $insDetails = "INSERT INTO `" . DB_TABLE_PREFIX_2 . "order_detail` SET "
                                . "`id_order`=" . (int) $OrderID . ", "
                                //. "`id_order_invoice`=, `id_warehouse`, "
                                . "`id_shop`='$rShop->id_shop', "
                                . "`product_id`='" . $rSOp->products_id . "', "
                                . "`product_attribute_id`='" . (int) $product_attribute . "', "
                                . "`product_name`='" . $PRODUCT_NAME . "', "
                                . "`product_quantity`='" . (int) $rSOp->products_quantity . "', "
                                //. "`product_quantity_in_stock`, `product_quantity_refunded`, `product_quantity_return`, `product_quantity_reinjected`, "
                                . "`product_price`='" . (float) $rSOp->final_price . "', "
                                //. "`reduction_percent`, `reduction_amount`, `reduction_amount_tax_incl`, `reduction_amount_tax_excl`, `group_reduction`, `product_quantity_discount`,  `id_tax_rules_group`, `tax_computation_method`, `tax_name`, 
                                . "`tax_rate`=" . (float) $rSOp->products_tax . ","
                                . "`product_reference`='" . (($resTargetProduct && $resTargetProduct->num_rows > 0) ? $rTProd->reference : '') . "', "
                                . "`product_supplier_reference`='" . (($resTargetProduct && $resTargetProduct->num_rows > 0) ? $rTProd->supplier_reference : '') . "',"
                                . "`product_ean13`='" . (($resTargetProduct && $resTargetProduct->num_rows > 0) ? $rTProd->ean13 : '') . "', "
                                . "`product_upc`='" . (($resTargetProduct && $resTargetProduct->num_rows > 0) ? $rTProd->upc : '') . "',"
                                . "`product_weight`='" . (($resTargetProduct && $resTargetProduct->num_rows > 0) ? ($rTProd->weight == 0 ? $orders_shipping_weight : $rTProd->weight) : '') . "',"
                                // `ecotax`, `ecotax_tax_rate`, `discount_quantity_applied`, `download_hash`, `download_nb`, `download_deadline`, 
                                . "`total_price_tax_incl`=" . (float) ((float) add_tax($rSOp->final_price, $rSOp->products_tax, true) * (int) $rSOp->products_quantity) . ", "
                                . "`total_price_tax_excl`=" . (float) ((float) ($rSOp->final_price) * (int) $rSOp->products_quantity) . ", "
                                . "`unit_price_tax_incl`='" . (float) add_tax($rSOp->final_price, $rSOp->products_tax, true) . "', "
                                . "`unit_price_tax_excl`='" . (float) ($rSOp->final_price) . "', "
                                . "`total_shipping_price_tax_incl`='$orders_shipping', "
                                . "`total_shipping_price_tax_excl`='$orders_shipping', "
                                . "`purchase_supplier_price`='" . (float) $rSOp->final_price . "',"
                                . "`original_product_price`='" . (float) $rSOp->final_price . "', "
                                . "`original_wholesale_price`='" . (float) $rSOp->final_price . "';";
                        LogMessage($insDetails);
                        if (!$dbTarget->query($insDetails)) {
                            //$dbTarget->rollback();
                            continue;
                        }
                    }

                    $priceShip = $orders_shipping; //getShipPriceByWeightCarrierID($row->weight, $carrierid);
                    $inscareer = "INSERT INTO `" . DB_TABLE_PREFIX_2 . "order_carrier` "
                            . "(`id_order`, `id_carrier`, `id_order_invoice`, `weight`, `shipping_cost_tax_excl`, `shipping_cost_tax_incl`, `tracking_number`, `date_add`) "
                            . "VALUES "
                            . "(" . (int) $OrderID . ", " . $carrierid . ", NULL, '" . ($row->weight > 0 ? $row->weight : $orders_shipping_weight) . "', $priceShip, $priceShip, '', '" . date("Y-m-d H:i:s", strtotime($row->date_purchased)) . "');";
                    LogMessage($inscareer);
                    if (!$dbTarget->query($inscareer)) {
                        //$dbTarget->rollback();
                        continue;
                    }
                    $sqlStatusHistory = "SELECT * FROM `" . DB_TABLE_PREFIX_1 . "orders_status_history` "
                            . "WHERE orders_id=" . (int) $OrderID
                            . " ORDER BY orders_status_history_id ASC";
                    $resOrderStatus = $dbSource->query($sqlStatusHistory);
                    if ($resOrderStatus) {
                        while ($rSStus = $resOrderStatus->fetch_object()) {
                            $insHist = "INSERT INTO `" . DB_TABLE_PREFIX_2 . "order_history` (`id_employee`, `id_order`, `id_order_state`, `date_add`) VALUES (0, " . (int) $OrderID . ", " . (int) getMatchingOrderStatus($rSStus->orders_status_id, $row->payment_method) . ", '" . $rSStus->date_added . "');";
                            LogMessage($insHist);
                            if (!$dbTarget->query($insHist)) {
                                //$dbTarget->rollback();
                                continue;
                            }
                        }
                    }
                    $OrderCartRuleID = 0;
                    if ($orders_discount > 0) {
                        $possible_cart_rule_id = getCartRule($orders_discount, $order_subtotal, date("Y-m-d", strtotime($row->date_purchased)));
                        $rule_name = getCartRule($orders_discount, $order_subtotal, date("Y-m-d", strtotime($row->date_purchased)), true);
                        if ($possible_cart_rule_id) {
                            $insCartRule = "INSERT INTO `" . DB_TABLE_PREFIX_2 . "cart_cart_rule` SET "
                                    . "id_cart=" . (int) $cartID . ", "
                                    . "id_cart_rule=" . (int) $possible_cart_rule_id;
                            LogMessage($insCartRule);
                            if (!$dbTarget->query($insCartRule)) {
                                //$dbTarget->rollback();
                                continue;
                            }
                            $arrayOrderCartRule = array(
                                'id_order' => (int) $OrderID,
                                'id_cart_rule' => (int) $possible_cart_rule_id,
                                'id_order_invoice' => 0,
                                'name' => $rule_name,
                                'value' => $orders_discount,
                                'value_tax_excl' => $orders_discount,
                                'free_shipping' => 0,
                            );
                            $OrderCartRuleID = insert($dbTarget, DB_TABLE_PREFIX_2 . 'order_cart_rule', $arrayOrderCartRule);
                        }
                    }
                    //Invoice
                    $arrayInvoice = array(
                        'id_order' => $OrderID,
                        'number' => $OrderID,
                        'total_discount_tax_excl' => $orders_discount,
                        'total_discount_tax_incl' => $orders_discount,
                        'total_paid_tax_excl' => $order_total,
                        'total_paid_tax_incl' => $order_total,
                        'total_products' => $order_subtotal,
                        'total_products_wt' => $order_subtotal,
                        'total_shipping_tax_excl' => $orders_shipping,
                        'total_shipping_tax_incl' => $orders_shipping,
                        'shop_address' => $row->customers_street_address . "\n"
                        . $row->customers_suburb . "\n"
                        . $row->customers_city . ", $row->customers_state, $row->customers_country $row->customers_postcode",
                        'invoice_address' => $row->billing_street_address . "\n"
                        . $row->billing_suburb . "\n"
                        . $row->billing_city . ", $row->billing_state, $row->billing_country $row->billing_postcode",
                        'delivery_address' => $row->delivery_street_address . "\n"
                        . $row->delivery_suburb . "\n"
                        . $row->delivery_city . ", $row->delivery_state, $row->delivery_country $row->delivery_postcode",
                        'note' => $row->delivery_special_instructions,
                        'date_add' => date("Y-m-d", strtotime($row->date_purchased))
                    );
                    $invoiceID = insert($dbTarget, DB_TABLE_PREFIX_2 . 'order_invoice', $arrayInvoice);
                    //Payment
                    $arrayPayment = array(
                        'order_reference' => $reference,
                        'id_currency' => (int) $id_currency,
                        'amount' => $order_total,
                        'payment_method' => $row->payment_method,
                        'conversion_rate' => '1.000000',
                        'transaction_id' => $row->transaction_id,
                        'card_number' => $row->cc_number,
                        'card_brand' => $row->cc_type,
                        'card_expiration' => $row->cc_expires,
                        'card_holder' => $row->cc_owner,
                        'date_add' => date("Y-m-d", strtotime($row->date_purchased))
                    );
                    $PaymentID = insert($dbTarget, DB_TABLE_PREFIX_2 . 'order_payment', $arrayPayment);

                    //Order Invoice Payment
                    $arrayOrderInvoicePayment = array(
                        'id_order_invoice' => $invoiceID,
                        'id_order_payment' => $PaymentID,
                        'id_order' => $OrderID
                    );
                    $OrderInvoicePID = insert($dbTarget, DB_TABLE_PREFIX_2 . 'order_invoice_payment', $arrayOrderInvoicePayment);
                    $upd1 = "UPDATE `" . DB_TABLE_PREFIX_2 . "orders` SET invoice_number=" . (int) $invoiceID . ",delivery_number=" . (int) $OrderID . " WHERE id_order=" . (int) $OrderID;
                    LogMessage($upd1);
                    if (!$dbTarget->query($upd1)) {
                        //$dbTarget->rollback();
                        continue;
                    }
                    $upd2 = "UPDATE `" . DB_TABLE_PREFIX_2 . "order_detail` SET id_order_invoice=" . (int) $invoiceID . " WHERE id_order=" . (int) $OrderID;
                    LogMessage($upd2);
                    if (!$dbTarget->query($upd2)) {
                        //$dbTarget->rollback();
                        continue;
                    }
                    $upd3 = "UPDATE `" . DB_TABLE_PREFIX_2 . "order_carrier` SET id_order_invoice=" . (int) $invoiceID . " WHERE id_order=" . (int) $OrderID;
                    LogMessage($upd3);
                    if (!$dbTarget->query($upd3)) {
                        //$dbTarget->rollback();
                        continue;
                    }
                    if (!empty($row->delivery_special_instructions)) {
                        $upd4 = "UPDATE `" . DB_TABLE_PREFIX_2 . "customer` SET note='" . $dbTarget->real_escape_string($row->delivery_special_instructions) . "' WHERE id_customer=" . (int) $row->customers_id;
                        LogMessage($upd4);
                        if (!$dbTarget->query($upd4)) {
                            //$dbTarget->rollback();
                            continue;
                        }
                    }
                    if ($OrderCartRuleID > 0) {
                        $upd5 = "UPDATE `" . DB_TABLE_PREFIX_2 . "order_cart_rule` SET id_order_invoice=" . (int) $invoiceID . " WHERE id_order_cart_rule=" . (int) $OrderCartRuleID;
                        LogMessage($upd5);
                        if (!$dbTarget->query($upd5)) {
                            //$dbTarget->rollback();
                            continue;
                        }
                    }
                } else {
                    $error = "Oops, Order products Not found (In OSCommerce Source DB) for orderid: " . (int) $row->orders_id;
                    LogMessage($error);
                    WriteErrorLog($error);
                }

                $message = "Successfully added order. OrderID: " . $OrderID . "";
                LogMessage($message);
                WriteSuccessLog($message);
            } else {
                $error = "Oops Cart not added for orderID:" . (int) $row->orders_id;
                LogMessage($error);
                WriteErrorLog($error);
            }
        }
        //$dbTarget->commit();//Not working on Old version...
    }
    mysqli_close($dbSource);
    if (!USE_API) {
        mysqli_close($dbTarget);
    }
} else {
    LogMessage("Query failed..." . $query);
}
/* close statement */
echo "ENDED\n";
