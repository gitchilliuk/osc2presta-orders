<?php

/**
 * Random password generator
 *
 * @param int $lengthInput Desired length (optional)
 * @param string $flag Output type (NUMERIC, ALPHANUMERIC, NO_NUMERIC, RANDOM)
 * @return bool|string Password
 */
$id_currency = getDefaultValue("PS_CURRENCY_DEFAULT");
$id_language = getDefaultValue("PS_LANG_DEFAULT");

function passwdGen($lengthInput = 8, $flag = 'ALPHANUMERIC') {
    $length = (int) $lengthInput;
    $result = false;
    if ($length > 0) {
        switch ($flag) {
            case 'NUMERIC':
                $str = '0123456789';
                break;
            case 'NO_NUMERIC':
                $str = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'RANDOM':
                $num_bytes = ceil($length * 0.75);
                $bytes = getBytes($num_bytes);
                return substr(rtrim(base64_encode($bytes), '='), 0, $length);
            case 'ALPHANUMERIC':
            default:
                $str = 'abcdefghijkmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
        }
        $bytes = getBytes($length);
        $position = 0;
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $position = ($position + ord($bytes[$i])) % strlen($str);
            $result .= $str[$position];
        }
    }
    return $result;
}

/**
 * Random bytes generator
 *
 * Thanks to Zend for entropy
 *
 * @param $lengthInput Desired length of random bytes
 * @return bool|string Random bytes
 */
function getBytes($lengthInput) {
    $length = (int) $lengthInput;
    if ($length <= 0) {
        return false;
    }

    if (function_exists('openssl_random_pseudo_bytes')) {
        $bytes = openssl_random_pseudo_bytes($length, $crypto_strong);
        if ($crypto_strong === true) {
            return $bytes;
        }
    }

    if (function_exists('mcrypt_create_iv')) {
        $bytes = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
        if ($bytes !== false && strlen($bytes) === $length) {
            return $bytes;
        }
    }

    // Else try to get $length bytes of entropy.
    // Thanks to Zend

    $result = '';
    $entropy = '';
    $msec_per_round = 400;
    $bits_per_round = 2;
    $total = $length;
    $hash_length = 20;

    while (strlen($result) < $length) {
        $bytes = ($total > $hash_length) ? $hash_length : $total;
        $total -= $bytes;

        for ($i = 1; $i < 3; $i++) {
            $t1 = microtime(true);
            $seed = mt_rand();

            for ($j = 1; $j < 50; $j++) {
                $seed = sha1($seed);
            }

            $t2 = microtime(true);
            $entropy .= $t1 . $t2;
        }

        $div = (int) (($t2 - $t1) * 1000000);

        if ($div <= 0) {
            $div = 400;
        }

        $rounds = (int) ($msec_per_round * 50 / $div);
        $iter = $bytes * (int) (ceil(8 / $bits_per_round));

        for ($i = 0; $i < $iter; $i ++) {
            $t1 = microtime();
            $seed = sha1(mt_rand());

            for ($j = 0; $j < $rounds; $j++) {
                $seed = sha1($seed);
            }

            $t2 = microtime();
            $entropy .= $t1 . $t2;
        }

        $result .= sha1($entropy, true);
    }

    return substr($result, 0, $length);
}

function generateReference() {
    return strtoupper(passwdGen(9, 'NO_NUMERIC'));
}

function getMatchingOrderStatus($sourceOrderStatusId, $sourcePaymentMethod) {
    global $dbSource, $dbTarget, $id_language;
    $resSourceStatusName = $dbSource->query("SELECT orders_status_name FROM `" . DB_TABLE_PREFIX_1 . "orders_status` WHERE language_id=1 AND orders_status_id=" . (int) $sourceOrderStatusId);
    $rStatusNameSource = $resSourceStatusName->fetch_object();
    $arrStatus = explode(" ", $rStatusNameSource->orders_status_name);
    $return_status_id = NULL;
    switch (strtolower($arrStatus[0])) {
        CASE 'pymt':
            $res = $dbTarget->query("SELECT id_order_state  FROM `" . DB_TABLE_PREFIX_2 . "order_state_lang` WHERE id_lang=$id_language AND name LIKE '%Pymt incomplete [PayPal]%'");
            $r = $res->fetch_object();
            $return_status_id = $r->id_order_state;
            break;
        CASE 'pending':
        CASE 'preparing':
        CASE 'voided':
            if (stristr($sourcePaymentMethod, 'paypal')) {
                if (strtolower($arrStatus[0]) == 'preparing') {
                    $res00 = $dbTarget->query("SELECT id_order_state  FROM `" . DB_TABLE_PREFIX_2 . "order_state_lang` WHERE id_lang=$id_language AND name LIKE '%Preparing [PayPal Pro Hosted]%'");
                    $r00 = $res00->fetch_object();
                    $return_status_id = $r00->id_order_state;
                } else if (strtolower($arrStatus[0]) == 'voided') {
                    $res01 = $dbTarget->query("SELECT id_order_state  FROM `" . DB_TABLE_PREFIX_2 . "order_state_lang` WHERE id_lang=$id_language AND name LIKE '%Voided [PayPal Pro Hosted]%'");
                    $r01 = $res01->fetch_object();
                    $return_status_id = $r01->id_order_state;
                } else {
                    $res0 = $dbTarget->query("SELECT id_order_state  FROM `" . DB_TABLE_PREFIX_2 . "order_state_lang` WHERE id_lang=$id_language AND name LIKE '%Awaiting PayPal payment%'");
                    $r0 = $res0->fetch_object();
                    $return_status_id = $r0->id_order_state;
                }
            } else if (stristr($sourcePaymentMethod, 'rbs') || stristr($sourcePaymentMethod, 'worldpay')) {
                $res1 = $dbTarget->query("SELECT id_order_state  FROM `" . DB_TABLE_PREFIX_2 . "order_state_lang` WHERE id_lang=$id_language AND ((name LIKE '%Awaiting%' OR name LIKE '%pending%') AND  name like '%worldpay%')");
                $r1 = $res1->fetch_object();
                $return_status_id = $r1->id_order_state;
            } else {
                $res2 = $dbTarget->query("SELECT id_order_state  FROM `" . DB_TABLE_PREFIX_2 . "order_state_lang` WHERE id_lang=$id_language AND (name LIKE '%Awaiting check payment%' OR name LIKE '%Awaiting bank wire payment%') LIMIT 1");
                $r2 = $res2->fetch_object();
                $return_status_id = $r2->id_order_state;
            }
            break;

        CASE 'processing':
            $res3 = $dbTarget->query("SELECT id_order_state  FROM `" . DB_TABLE_PREFIX_2 . "order_state_lang` WHERE id_lang=$id_language AND (name LIKE '%Processing%' OR name LIKE '%in progress%') LIMIT 1");
            $r3 = $res3->fetch_object();
            $return_status_id = $r3->id_order_state;
            break;
        CASE 'despatched':
            $res4 = $dbTarget->query("SELECT id_order_state  FROM `" . DB_TABLE_PREFIX_2 . "order_state_lang` WHERE id_lang=$id_language AND name LIKE '%Shipped%' LIMIT 1");
            $r4 = $res4->fetch_object();
            $return_status_id = $r4->id_order_state;
            break;
        /* CASE 'packing':
          break;
          CASE 'packed':
          break; */
        default:
            $res5 = $dbTarget->query("SELECT id_order_state  FROM `" . DB_TABLE_PREFIX_2 . "order_state_lang` WHERE id_lang=$id_language AND name LIKE '%" . strtolower($arrStatus[0]) . "%' LIMIT 1");
            if ($res5) {
                $r5 = $res5->fetch_object();
                $return_status_id = $r5->id_order_state;
            }
    }
    return $return_status_id;
}

function getModuleName($modnameInput = 'paypal') {
    global $dbTarget, $id_language;
    $arrmodnameInput = explode(" ", $modnameInput);
    $modname = $dbTarget->real_escape_string(strtolower($arrmodnameInput[0]));
    $result = $dbTarget->query("SELECT name  FROM `" . DB_TABLE_PREFIX_2 . "module` WHERE CONVERT(`name` USING utf8) LIKE '%$modname%'");
    $rsModuleName = $result->fetch_object();
    return $rsModuleName->name;
}

function getCountryIdByName($name) {
    global $dbTarget, $id_language;
    $retun = 'null';
    $country_name = $dbTarget->real_escape_string(trim(preg_replace('/(.*?)\((.*?)\)(.*?)/', '$1', $name)));
    $sql = "SELECT id_country FROM `" . DB_TABLE_PREFIX_2 . "country_lang` WHERE name LIKE '%$country_name%' AND id_lang=$id_language";
    //echo $sql."\n+++++*******++++++\n";
    $result = $dbTarget->query($sql);
    if ($result) {
        $rscountryid = $result->fetch_object();
        $retun = $rscountryid->id_country;
    }
    return $retun;
}

function getStateIdByName($name) {
    global $dbTarget, $id_language;
    $ret = 'null';
    $state_name = $dbTarget->real_escape_string(trim(preg_replace('/(.*?)\((.*?)\)(.*?)/', '$1', $name)));
    $sql = "SELECT id_state FROM `" . DB_TABLE_PREFIX_2 . "state` WHERE name LIKE '%$state_name%' AND id_lang=$id_language";
    //echo $sql."\n+++++*******++++++\n";
    $result = $dbTarget->query($sql);
    if ($result) {
        $stateid = $result->fetch_object();
        $ret = $stateid->id_state;
    }
    return $ret;
}

function WriteLastID($filename, $id) {
    $fp = fopen($filename, "w+");
    fwrite($fp, $id);
    fclose($fp);
}

function WriteErrorLog($msg) {
    $fp = fopen(__DIR__ . "/ErrorMessages_" . date("Ymd") . ".txt", "a+");
    fwrite($fp, trim($msg, "\n") . "\n");
    fclose($fp);
}

function WriteSuccessLog($msg) {
    $fp = fopen(__DIR__ . "/SuccessMessages_" . date("Ymd") . ".txt", "a+");
    fwrite($fp, trim($msg, "\n") . "\n");
    fclose($fp);
}

function add_tax($price, $tax, $override = false) {
    return $price + calculate_tax($price, $tax);
}

// Calculates Tax rounding the result
function calculate_tax($price, $tax) {
    return $price * $tax / 100;
}

function getCartRule($discount, $tot, $orderdate, $bName = false) {
    global $dbTarget, $id_language;
    $ret = false;
    //$sql="SELECT * FROM `" . DB_TABLE_PREFIX_2 . "cart_rule` WHERE date('$orderdate') BETWEEN date(date_from) AND date(date_to)";//
    $sql = "SELECT SQL_CALC_FOUND_ROWS b.name, a.* "
            . "FROM `" . DB_TABLE_PREFIX_2 . "cart_rule` a "
            . "LEFT JOIN `" . DB_TABLE_PREFIX_2 . "cart_rule_lang` b "
            . "ON (b.`id_cart_rule` = a.`id_cart_rule` AND b.`id_lang` = $id_language) "
            . "WHERE date('$orderdate') BETWEEN date(a.date_from) AND date(a.date_to)  "
            . "ORDER BY a.`id_cart_rule` DESC";
    LogMessage("\n***********************\n$sql\n****************************\n");
    $result = $dbTarget->query($sql); //
    while ($result && $cr = $result->fetch_object()) {
        if ($cr->reduction_percent > 0) {
            $subtot_calc = (float) (($discount * 100) / $cr->reduction_percent);
            LogMessage("\n***********************\n$subtot_calc = (float) (($discount * 100) / $cr->reduction_percent);\n****************************\n");
            LogMessage("\n***********************\nTotal Passed=$tot and Calculated=$subtot_calc discount passed=$discount\n****************************\n");
            if (ceil($tot) == ceil($subtot_calc)) {
                LogMessage("\n***********************\nHURRRY FOUND THE ID: $cr->id_cart_rule\n****************************\n");
                if ($bName) {
                    return $cr->name;
                }
                return $cr->id_cart_rule;
            }
        } else if (ceil($discount) == ceil($cr->reduction_amount)) {
            LogMessage("\n***********************\nHURRRY FOUND FLAT DISCOUNT ID: $cr->id_cart_rule\n****************************\n");
            if ($bName) {
                return $cr->name;
            }
            return $cr->id_cart_rule;
        }
        LogMessage("\n00000000000000000 = discount passed=$discount and Reduction amount=$cr->reduction_amount\n****************************\n");
    }

    return $ret;
}

function LogMessage($message) {
    if (PHP_SAPI === 'cli') {
        if (error_log($message)) {
            /*$stderr = fopen('php://stderr', 'w');
            fwrite($stderr, "\n" . $message . "\n");
            fclose($stderr);*/
        }
    } else {
        //echo nl2br($message) . "<br>";
    }
    /*$stderr = fopen(__DIR__ . '/ScreenMessage' . date("dmY") . '.txt', 'a+');
    fwrite($stderr, $message . "\n");
    fclose($stderr);*/
}

function is_asso($a) {
    foreach (array_keys($a) as $key) {
        if (!is_int($key)) {
            return TRUE;
        }
    }
    return FALSE;
}

function insert($db, $tablename, $fields = array()) {
    $return = false;
    $ins = "INSERT INTO `$tablename` SET ";
    if (!empty($fields)) {
        if (!is_asso($fields)) {
            trigger_error("Argument should be an associative array, example array('name'=>'John');", E_STRICT);
        }
        foreach ($fields as $key => $val) {
            $ins .= "`" . trim($key) . "`='" . $db->real_escape_string(trim($val)) . "', ";
        }
        $insertQuery = trim($ins, ", ");
        LogMessage("\n" . $insertQuery . "\n");
        if ($db->query($insertQuery)) {
            $return = $db->insert_id;
        }
    } else {
        trigger_error("Empty field list", E_STRICT);
    }
    return $return;
}

function getCarrierId($shipping_type) {
    global $dbTarget, $id_language;
    $return = false;
    switch ($shipping_type) {
        CASE 'regionspallet_regionspallet':
            $sql = "SELECT  a.*, b.* FROM `" . DB_TABLE_PREFIX_2 . "carrier` a 
                INNER JOIN `" . DB_TABLE_PREFIX_2 . "carrier_lang` b ON a.id_carrier = b.id_carrier AND b.id_shop = 1  AND b.id_lang = $id_language 
                LEFT JOIN `" . DB_TABLE_PREFIX_2 . "carrier_tax_rules_group_shop` ctrgs ON (a.`id_carrier` = ctrgs.`id_carrier` AND ctrgs.id_shop=1) 
                WHERE a.name LIKE '%pallet%'  AND a.`deleted` = 0 
                ORDER BY a.`position` ASC";
            $result0 = $dbTarget->query($sql);
            if ($result0 && $result0->num_rows > 0) {
                $r0 = $result0->fetch_object();
                $return = $r0->id_carrier;
            }
            break;
        CASE 'regions_regions':
            $sql1 = "SELECT  a.*, b.* FROM `" . DB_TABLE_PREFIX_2 . "carrier` a 
                INNER JOIN `" . DB_TABLE_PREFIX_2 . "carrier_lang` b ON a.id_carrier = b.id_carrier AND b.id_shop = 1  AND b.id_lang = $id_language 
                LEFT JOIN `" . DB_TABLE_PREFIX_2 . "carrier_tax_rules_group_shop` ctrgs ON (a.`id_carrier` = ctrgs.`id_carrier` AND ctrgs.id_shop=1) 
                WHERE a.name LIKE '%courier%'  AND a.`deleted` = 0 
                ORDER BY a.`position` ASC";
            $result1 = $dbTarget->query($sql1);
            if ($result1 && $result1->num_rows > 0) {
                $r1 = $result1->fetch_object();
                $return = $r1->id_carrier;
            }
            break;
        CASE 'ukpost_ukpost':
            $sql2 = "SELECT  a.*, b.* FROM `" . DB_TABLE_PREFIX_2 . "carrier` a 
                INNER JOIN `" . DB_TABLE_PREFIX_2 . "carrier_lang` b ON a.id_carrier = b.id_carrier AND b.id_shop = 1  AND b.id_lang = $id_language 
                LEFT JOIN `" . DB_TABLE_PREFIX_2 . "carrier_tax_rules_group_shop` ctrgs ON (a.`id_carrier` = ctrgs.`id_carrier` AND ctrgs.id_shop=1) 
                WHERE (a.name LIKE '%ukpost%' OR a.name LIKE '%royal mail%')  AND a.`deleted` = 0 
                ORDER BY a.`position` ASC";
            $result2 = $dbTarget->query($sql2);
            if ($result2 && $result2->num_rows > 0) {
                $r2 = $result2->fetch_object();
                $return = $r2->id_carrier;
            }
            break;
        default:
            $sql3 = "SELECT  a.*, b.* FROM `" . DB_TABLE_PREFIX_2 . "carrier` a 
                INNER JOIN `" . DB_TABLE_PREFIX_2 . "carrier_lang` b ON a.id_carrier = b.id_carrier AND b.id_shop = 1  AND b.id_lang = $id_language 
                LEFT JOIN `" . DB_TABLE_PREFIX_2 . "carrier_tax_rules_group_shop` ctrgs ON (a.`id_carrier` = ctrgs.`id_carrier` AND ctrgs.id_shop=1) 
                WHERE (a.name LIKE '%CC Moore%')  AND a.`deleted` = 0 
                ORDER BY a.`position` ASC";
            $result3 = $dbTarget->query($sql3);
            if ($result3 && $result3->num_rows > 0) {
                $r3 = $result3->fetch_object();
                $return = $r3->id_carrier;
            }
    }
    return $return;
}

function getShipPriceByWeightCarrierID($weight, $carrierid) {
    global $dbTarget;
    $ret = 'NULL';
    $sql = "SELECT d.price,rw.* "
            . "FROM `" . DB_TABLE_PREFIX_2 . "delivery` d "
            . "INNER JOIN  `" . DB_TABLE_PREFIX_2 . "range_weight` rw ON(d.id_range_weight=rw.id_range_weight) "
            . "WHERE rw.id_carrier=" . (int) $carrierid . " AND '$weight' between delimiter1 and delimiter2 "
            . "GROUP BY rw.id_carrier";
    LogMessage($sql);
    $result0 = $dbTarget->query($sql);
    if ($result0 && $result0->num_rows > 0) {
        $r0 = $result0->fetch_object();
        $ret = $r0->price;
    }
    return $ret;
}

function getDefaultValue($nameInput) {
    global $dbTarget;
    $ret = false;
    $name = $dbTarget->real_escape_string(trim($nameInput));
    $sql = "SELECT name,value FROM `" . DB_TABLE_PREFIX_2 . "configuration` WHERE name LIKE '%$name%' LIMIT 1";
    $result0 = $dbTarget->query($sql);
    if ($result0 && $result0->num_rows > 0) {
        $r0 = $result0->fetch_object();
        $ret = $r0->value;
    }
    return $ret;
}

function getCustomerGroupId($typeInput = 'customer') {
    global $dbTarget, $id_language;
    $ret = false;
    $type = $dbTarget->real_escape_string(trim($typeInput));
    $sql = "SELECT id_group FROM `" . DB_TABLE_PREFIX_2 . "group_lang` WHERE name LIKE '%$type%' AND id_lang = $id_language LIMIT 1";
    $result0 = $dbTarget->query($sql);
    if ($result0 && $result0->num_rows > 0) {
        $r0 = $result0->fetch_object();
        $ret = $r0->id_group;
    }
    return $ret;
}
