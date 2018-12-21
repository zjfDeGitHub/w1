<?php
/**
 * 微信红包的类
 * @Author snmoney#gmail.com
 * @blog http://snmoney.blog.163.com
 * @copyright 2015
 * @version 2.0
 *
 * 本类可任意授权使用，务必保留作者信息以便侦错改进；
 * 对因修改源码产生直接或间接经济损失，作者不承担任何责任。
 *
 * *微信红包还有部分可选的参数，如分享预设值等将在后续版本补充上相关功能。
 *
 * 更新
 * v2.0 2015-9-21
 *      对应官方接口更新，追加了分裂红包的玩法，详情参考官方文档。
 */

class WXHongBao {

    private $mch_id = MCHID;//商户ID写死
    private $wxappid = APPID;//微信公众号，写死
    private $client_ip = "172.17.14.161"; //调用红包接口的主机的IP,服务端IP,写死，即脚本文件所在的IP
    private $apikey = PARTNERKEY;//pay的秘钥值
    private $total_num = 1;//发放人数。固定值1，不可修改
    private $nick_name = "XX公众号"; //红包商户名称
    private $send_name = "XX公司";//红包派发者名称
    private $wishing = "祝福语"; //
    private $act_name = "红包活动"; //活动名称
    private $remark = "活动备注";
    private $nonce_str = "";
    private $mch_billno = "";
    private $re_openid = "";//接收方的openID
    private $total_amount = 1 ;//红包金额，单位 分
    private $min_value = 1;//最小金额
    private $max_value = 1; //根据接口要求，上述3值必须一致
    private $sign = ""; //签名在send时生成
    private $amt_type; //分裂红包参数，在sendgroup中进行定义，是常量 ALL_RAND

    //证书，在构造函数中定义，注意！
    private $apiclient_cert; //= getcwd()."/apiclient_cert.pem";
    private $apiclient_key;// = getcwd()."/apiclient_key.pem";

    //分享参数
    private $isShare = false; //有用？似乎是无用参数，全部都不是必选和互相依赖的参数
    private $share_content = "";
    private $share_url ="";
    private $share_imgurl = "";

    private $wxhb_inited;

    private $api_hb_group = "https://api.mch.weixin.qq.com/mmpaymkttransfers/sendgroupredpack";//裂变红包
    private $api_hb_single = "https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack";

    private $error = "ok"; //init



    /**
     * WXHongBao::__construct()
     * 步骤
     * new(openid,amount)
     * setnickname
     * setsend_name
     * setwishing
     * setact_name
     * setremark
     * send()
     * @return void
     */
    function __construct(){
        //好像没有什么需要构造函数做的
        $this->wxhb_inited = false;
        $this->apiclient_cert = SSLCERT_PATH;
        $this->apiclient_key = SSLKEY_PATH;
    }

    public function err(){
        return $this->error;
    }
    public function error(){
        return $this->err();
    }
    /**
     * WXHongBao::newhb()
     * 构造新红包
     * @param mixed $toOpenId
     * @param mixed $amount 金额分
     * @return void
     */
    public function newhb($toOpenId,$amount){

        if(!is_numeric($amount)){
            $this->error = "金额参数错误";
            return;
        }elseif($amount<100){
            $this->error = "金额太小";
            return;
        }elseif($amount>20000){
            $this->error = "金额太大";
            return;
        }

        $this->gen_nonce_str();//构造随机字串
        $this->gen_mch_billno();//构造订单号
        $this->setOpenId($toOpenId);
        $this->setAmount($amount);
        $this->wxhb_inited = true; //标记微信红包已经初始化完毕可以发送

        //每次new 都要将分享的内容给清空掉，否则会出现残余被引用
        $this->share_content= "";
        $this->share_imgurl = "";
        $this->share_url = "";
    }

    /**
     * WXHongBao::send()
     * 发出红包
     * 构造签名
     * 注意第二参数，单发时不要改动！
     * @return boolean $success
     */
    public function send($url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack",$total_num = 1){
        if(!$this->wxhb_inited){
            $this->error .= "(红包未准备好)";
           // return false; //未初始化完成
        }

        $this->total_num = $total_num;

        $this->gen_Sign(); //生成签名

        //构造提交的数据
        $xml = $this->genXMLParam();

        //debug
        file_put_contents("hbxml.debug",$xml);

        //提交xml,curl
        //$url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack";
        $ch = curl_init();
    	curl_setopt($ch,CURLOPT_TIMEOUT,10);
    	curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
    	curl_setopt($ch,CURLOPT_URL,$url);
    	curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
    	curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);

    	curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
    	curl_setopt($ch,CURLOPT_SSLCERT,$this->apiclient_cert);
    	curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
    	curl_setopt($ch,CURLOPT_SSLKEY,$this->apiclient_key);

        /*
    	if( count($aHeader) >= 1 ){
    		curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
    	}
        */
    	curl_setopt($ch,CURLOPT_POST, 1);
    	curl_setopt($ch,CURLOPT_POSTFIELDS,$xml);
    	$data = curl_exec($ch);
		print_r($data);
		//echo "滴答滴答滴答的";
    	if($data){
    	    curl_close($ch);
    		$rsxml = simplexml_load_string($data);
            if($rsxml->return_code == 'SUCCESS' ){
                return true;
            }else{
                $this->error = $rsxml->return_msg;
                return false;
            }

    	}else{
    		$this->error = curl_errno($ch);

    		curl_close($ch);
    		return false;
    	}

    }

    /**
     * WXHongBao::sendGroup()
     * 发送裂变红包,参数为裂变数量
     * @param integer $num 3-20
     * @return
     */
    public function sendGroup($num=3){
        $this->amt_type = "ALL_RAND";//$amt; 固定值。发送裂变红包组文档指定参数，随机
        return $this->send($this->api_hb_group,$num);
    }

    public function getApiSingle(){
        return $this->api_hb_single;
    }

    public function getApiGroup(){
        return $this->api_hb_group;
    }

    public function setNickName($nick){
        $this->nick_name = $nick;
    }

    public function setSendName($name){
        $this->send_name = $name;
    }

    public function setWishing($wishing){
        $this->wishing = $wishing;
    }

    /**
     * WXHongBao::setActName()
     * 活动名称
     * @param mixed $act
     * @return void
     */
    public function setActName($act){
        $this->act_name = $act;
    }

    public function setRemark($remark){
        $this->remark = $remark;
    }

    public function setOpenId($openid){
        $this->re_openid = $openid;
    }

    /**
     * WXHongBao::setAmount()
     * 设置红包金额
     * 文档有两处冲突描述
     * 一处指金额 >=1 (分钱)
     * 另一处指金额 >=100 < 20000 [1-200元]
     * 有待测试验证！
     * @param mixed $price 单位 分
     * @return void
     */
    public function setAmount($price){
        $this->total_amount = $price;
        $this->min_value = $price;
        $this->max_value = $price;
    }
    //以下方法，为设置分裂红包时使用
    public function setHBminmax($min,$max){
        $this->min_value = $min;
        $this->max_value = $max;
    }


    public function setShare($img="",$url="",$content=""){

        //https://mmbiz.qlogo.cn/mmbiz/MS1jaDO92Ep4qNo9eV0rnItptyBrzUhJqT8oxSsCofdxibnNWMJiabaqgLPkDaEJmia6fqTXAXulKBa9NLfxYMwYA/0?wx_fmt=png
        //http://mp.weixin.qq.com/s?__biz=MzA5Njg4NTk3MA==&mid=206257621&idx=1&sn=56241da30e384e40771065051e4aa6a8#rd
        $this->share_content = $content;
        $this->share_imgurl = $img;
        $this->share_url = $url;
    }

    private function gen_nonce_str(){
        $this->nonce_str = strtoupper(md5(mt_rand().time())); //确保不重复而已
    }

    private function gen_Sign(){
        unset($param);
        //其实应该用key重排一次 right?
        $param["act_name"]=$this->act_name;//

        if($this->total_num==1){ //这些是裂变红包用不上的参数，会导致签名错误
            $param["client_ip"]=$this->client_ip;
            $param["max_value"]=$this->max_value;
            $param["min_value"]=$this->min_value;
            $param["nick_name"]=$this->nick_name;
        }

        $param["mch_billno"] = $this->mch_billno;   //
        $param["mch_id"]=$this->mch_id;//
        $param["nonce_str"]=$this->nonce_str;    //
        $param["re_openid"]=$this->re_openid;//
        $param["remark"]=$this->remark;        //
        $param["send_name"]=$this->send_name;//
        $param["total_amount"]=$this->total_amount;//
        $param["total_num"]=$this->total_num;        //
        $param["wishing"]=$this->wishing;//
        $param["wxappid"]=$this->wxappid;//

        if($this->share_content) $param["share_content"] = $this->share_content;
        if($this->share_imgurl) $param["share_imgurl"] = $this->share_imgurl;
        if($this->share_url) $param["share_url"] = $this->share_url;

        if($this->amt_type) $param["amt_type"] = $this->amt_type; //

        ksort($param); //按照键名排序...艹，上面排了我好久

        //$sign_raw = http_build_query($param)."&key=".$this->apikey;
        $sign_raw = "";
        foreach($param as $k => $v){
            $sign_raw .= $k."=".$v."&";
        }
        $sign_raw .= "key=".$this->apikey;

        //file_put_contents("sign.raw",$sign_raw);//debug
        $this->sign = strtoupper(md5($sign_raw));
    }

    /**
     * WXHongBao::genXMLParam()
     * 生成post的参数xml数据包
     * 注意生成之前各项值要生成，尤其是Sign
     * @return $xml
     */
    public function genXMLParam(){
        $xml = "<xml>
            <sign>".$this->sign."</sign> 
            <mch_billno>".$this->mch_billno."</mch_billno> 
            <mch_id>".$this->mch_id."</mch_id> 
            <wxappid>".$this->wxappid."</wxappid> 
            <nick_name><![CDATA[".$this->nick_name."]]></nick_name> 
            <send_name><![CDATA[".$this->send_name."]]></send_name> 
            <re_openid>".$this->re_openid."</re_openid> 
            <total_amount>".$this->total_amount."</total_amount> 
            <min_value>".$this->min_value."</min_value> 
            <max_value>".$this->max_value."</max_value> 
            <total_num>".$this->total_num."</total_num> 
            <wishing><![CDATA[".$this->wishing."]]></wishing> 
            <client_ip><![CDATA[".$this->client_ip."]]></client_ip> 
            <act_name><![CDATA[".$this->act_name."]]></act_name> 
            <remark><![CDATA[".$this->remark."]]></remark>             
            <nonce_str>".$this->nonce_str."</nonce_str>
            ";


        if($this->share_content) $xml .= "<share_content><![CDATA[".$this->share_content."]]></share_content>
        ";
        if($this->share_imgurl) $xml .= "<share_imgurl><![CDATA[".$this->share_imgurl."]]></share_imgurl>
        ";
        if($this->share_url) $xml .= "<share_url><![CDATA[".$this->share_url."]]></share_url>
        ";
        if($this->amt_type) $xml .= "<amt_type><![CDATA[".$this->amt_type."]]></amt_type>
        ";

        $xml .="</xml>";

        return $xml;
    }

    /**
     * WXHongBao::gen_mch_billno()
     *  商户订单号（每个订单号必须唯一）
        组成： mch_id+yyyymmdd+10位一天内不能重复的数字。
        接口根据商户订单号支持重入， 如出现超时可再调用。
     * @return void
     */
    private function gen_mch_billno(){
        //生成一个长度10，的阿拉伯数字随机字符串
        $rnd_num = array('0','1','2','3','4','5','6','7','8','9');
        $rndstr = "";
        while(strlen($rndstr)<10){
            $rndstr .= $rnd_num[array_rand($rnd_num)];
        }

        $this->mch_billno = $this->mch_id.date("Ymd").$rndstr;
    }
}
?>
