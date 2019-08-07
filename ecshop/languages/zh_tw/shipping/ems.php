<?php

/**
 * ECSHOP EMS插件的語言文件
 * ============================================================================
 * * 版權所有 2005-2018 上海商派網絡科技有限公司，並保留所有權利。
 * 網站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 這不是一個自由軟件！您只能在不用於商業目的的前提下對程序代碼進行修改和
 * 使用；不允許對程序代碼以任何形式任何目的的再發布。
 * ============================================================================
 * $Author: liubo $
 * $Id: ems.php 17217 2011-01-19 06:29:08Z liubo $
*/

$_LANG['ems']                   = 'EMS 國內郵政特快專遞';
$_LANG['ems_express_desc']      = 'EMS 國內郵政特快專遞描述內容';
//$_LANG['fee_compute_mode'] = '費用計算方式';
$_LANG['item_fee']              = '單件商品費用：';
$_LANG['base_fee']              = '500克以內費用：';
$_LANG['step_fee']              = '續重每500克或其零數的費用：';
$_LANG['shipping_print'] = '<table style="width:18.8cm" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td style="height:3.2cm;">&nbsp;</td>
  </tr>
</table>
<table style="width:18.8cm;" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td style="width:8.9cm;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr>
    <td style="width:1.6cm; height:0.7cm;">&nbsp;</td>
    <td style="width:2.3cm;">{$shop_name}</td>
    <td style="width:2cm;">&nbsp;</td>
    <td>{$service_phone}</td>
    </tr>
    <tr>
    <td colspan="4" style="height:0.7cm; padding-left:1.6cm;">{$shop_name}</td>
    </tr>
    <tr>
    <td>&nbsp;</td>
    <td colspan="3" style="height:1.7cm;">{$shop_address}</td>
    </tr>
    <tr>
    <td colspan="3" style="width:6.3cm; height:0.5cm;"></td>
    <td>&nbsp;</td>
    </tr>
    </table>
    </td>
    <td style="width:0.4cm;"></td>
    <td style="width:9.5cm;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr>
    <td style="width:1.6cm; height:0.7cm;">&nbsp;</td>
    <td style="width:2.3cm;">{$order.consignee}</td>
    <td style="width:2cm;">&nbsp;</td>
    <td>{$order.mobile}</td>
    </tr>
    <tr>
    <td colspan="4" style="height:0.7cm;">&nbsp;</td>
    </tr>
    <tr>
    <td>&nbsp;</td>
    <td colspan="3" style="height:1.7cm;">{$order.address}</td>
    </tr>
    <tr>
    <td colspan="3" style="width:6.3cm; height:0.5cm;"></td>
    <td>{$order.zipcode}</td>
    </tr>
    </table>
    </td>
  </tr>
</table>
<table style="width:18.8cm" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td style="height:5.1cm;">&nbsp;</td>
  </tr>
</table>';

?>