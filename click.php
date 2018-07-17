<?php

$this->registerJs('
    $("#click_form").submit();
    ');

$secret = '<KEY>';
$date = date("Y-m-d h:i:s");
$merchantID = <MERCHANTID>;
$merchantUserID = <MERCHANTUSERID>;
$serviceID = <SERVICEID>;
$transID = <ORDERID>;
$transAmount = number_format(<AMOUNT>, 2, '.', '');
$signString = md5 ($date. $secret. $serviceID. $transID. $transAmount);
$returnURL = <RETURNURL>;
?>

<form action="https://my.click.uz/pay/" id="click_form" method="post">

<input id="click_amount_field" type="hidden" name="MERCHANT_TRANS_AMOUNT" value="<?= $transAmount ?>" class="click_input" />
<input type="hidden" name="MERCHANT_ID" value="<?= $merchantID ?>"/>
<input type="hidden" name="MERCHANT_USER_ID" value="<?= $merchantUserID ?>"/>
<input type="hidden" name="MERCHANT_SERVICE_ID" value="<?= $serviceID ?>"/>
<input type="hidden" name="MERCHANT_TRANS_ID" value="<?= $transID ?>"/>
<input type="hidden" name="MERCHANT_TRANS_NOTE" value="Оплата OOO  MERCHANT"/>
<input type="hidden" name="MERCHANT_USER_PHONE" value="+998999999999"/>
<input type="hidden" name="MERCHANT_USER_EMAIL" value="mail@server.com"/>
<input type="hidden" name="SIGN_TIME" value="<?= $date ?>"/>
<input type="hidden" name="SIGN_STRING" value="<?= $signString ?>"/>
<input type="hidden" name="RETURN_URL" value="<?= $returnURL ?>"/>
                                                        
</form>

