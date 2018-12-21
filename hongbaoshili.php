<?php
$act = $_REQUEST['act'];
if ($act == 'jiesuan'){ //发放奖金
    $money =intval($_POST['money1']);
    $vipid = $_POST['vipid'];
    $vipinfo2 = $db->getRow("select * from wx_card_vip where id = $vipid");
   // $id=intval($_REQUEST['id']);
    if ($money < $vipinfo2['totalcommission']) {
        require("../hongbao/hongbao.php");
        $ser_number = 'XWTX' . time() . rand(0000, 9999);
        $addtime = time();
        $logarr = array(
            'vipid' => $vipinfo2['id'],
            'price' => $money,
            'status' => 1,
            'addtime' => $addtime,
            'ser_number' =>$ser_number
        );
        $db->insert("wx_incentive_log",$logarr);
        //2.自动入账发送红包
        //$sql="select *  from wx_incentive_score where  status=2 and id=".$id;
        // $incentiveinfo=$db->getRow($sql);
       // $vipinfo = $db->getRow("select wxid from wx_Card_Vip  where  id=" . $incentiveinfo["vipid"]);
        $datas = array(
            'nonce_str' => createNoncestr(32),        //随机字符串
            'mch_billno' => $ser_number,                //商户订单号
            'mch_id' => '1376483302',                                        //商户号
            'wxappid' => 'wx37667adeb5aa4c97',                                        //公众账号appid
            'send_name' => "熙旺便利店",                            //商户名称
            're_openid' => $vipinfo2["wxid"],                           //用户openid
            'total_amount' => $money * 100,            //付款金额
            'total_num' => 1,                                        //红包发放总人数
            'wishing' => "恭喜发财，大吉大利",                            //红包祝福语
            'client_ip' => "172.17.14.161",                            //Ip地址
            'act_name' => "订单返佣到账",                                //活动名称
            'remark' => "商城返现红包",        //备注
            'scene_id' => 'PRODUCT_5',                              //场景ID
        );

        $Signs = getSign($datas);//本地签名
        $datas["sign"] = $Signs;
        $dsourl = "https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack";
        $xml = arrayToXml($datas);
        $result = postXmlSSLCurl($xml, $dsourl);

        $reObj = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);
        $result_code = trim($reObj->result_code);
        $mch_billno = trim($reObj->mch_billno);

//		print_r($datas);
//		echo $result;
        if ($result_code == "SUCCESS") { //成功
            $logarr = array(
                'results' => $result,
                'successTime'=>time(),
                'result_code' => $result_code,
                'mch_billno' => $mch_billno,
                'status'=>2,
            );
            $db->update('wx_incentive_log', $logarr,"ser_number=".'"'.$ser_number.'"');
            $rearray = array(
               // 'results' => $result,
                //'result_code' => $result_code,
                //'mch_billno' => $mch_billno,
                'totalcommission' => $vipinfo2['totalcommission'] - $money,
                'commission_tx' => $vipinfo2['commission_tx'] + $money,
                //'status' => 2,
            );
            $db->update('wx_card_vip', $rearray, "id=".$vipinfo2['id']);
            echo "<script>alert('恭喜你,提现成功');location.href=\"main.php\"</script>";
            /***发送模版信息开始*/
            //订单支付成功通知
            $tid = "OPENTM207422813";
            $twxid = $vipinfo["wxid"];
            $template_message = $db->getRow("select * from wx_template_message where t_id='$tid' ");
            if ($template_message["t_status"] == 2 and $twxid <> "") { //已启用
                $total_fee = $incentive_score["tc_moneys"];
                $arraydata = array(
                    'first' => array('value' => "亲，您有一个红包待领取", 'color' => $template_message["t_font_colour"]),
                    'keyword1' => array('value' => $incentiveinfo["tc_moneys"] . "元", 'color' => $template_message["t_font_colour"]),
                    'keyword2' => array('value' => strip_tags(getincentive_info_types($incentive_score["types"], $incentive_score["odrtype"])), 'color' => $template_message["t_font_colour"]),
                    'keyword3' => array('value' => date('Y-m-d H:i', time()), 'color' => $template_message["t_font_colour"]),
                    'remark' => array('value' => "记得领取红包哦，过期就无法领取了哦", 'color' => $template_message["t_font_colour"])
                );
                $url = APIHOST . "allhy/incentive_score.php?act=moneyupinfo&serial_number=" . $incentive_score["serial_number"];
                $arraypost = array(
                    'touser' => $twxid,
                    'template_id' => $template_message["template_id"],
                    'url' => $url,
                    "topcolor" => $template_message["t_head_colour"],
                    'data' => $arraydata
                );

                $jsons = JSON($arraypost);
                $result = send_template_message($jsons);
                //print_r($result);

            }
            /***发送模版信息结束**/
            C::jump('红包发放成功', common::deCodePath($_REQUEST['selflink']));
        } else {
            echo "<script>alert('提现失败');location.href=\"main.php\"</script>";
            $logarr = array(
                'results' => $result,
                'result_code' => $result_code,
                'mch_billno' => $mch_billno,
                'status' => 3,

            );
            $db->update('wx_incentive_log', $logarr,"ser_number=".'"'.$ser_number.'"');

            $rearray = array(
//                'results' => $result,
//                'result_code' => $result_code,
//                'mch_billno' => $mch_billno,
//				'status'=>5
            );
            $db->update('wx_card_vip', $rearray, "id=" . $vipinfo2["id"]);
//            $db->update('wx_incentive_score',$rearray,"id=".$incentiveinfo["id"]);
            C::jump('红包发放失败：失败原因：' . $reObj->return_msg, common::deCodePath($_REQUEST['selflink']));
        }


        //2.自动入账发送红包


    }
    else{
        echo "<script>alert('提现金额不足');location.href=\"main.php\"</script>";

    }
}
?>
