<?php

/**
 *
 */
class Ezship
{
    /**
     * xml 接收 api url
     */
    private $xmlApiUrl = 'https://www.ezship.com.tw/emap/ezship_xml_order_api_ex.jsp';

    /**
     * 參數 接收 api url
     */
    private $parameterApiUrl = 'https://www.ezship.com.tw/emap/ezship_request_order_api_ex.jsp';

    /**
     * 電子地圖 api url
     */
    private $mapApiUrl = 'https://map.ezship.com.tw/ezship_map_web.jsp';

    /**
     * 貨物狀態查詢 api url
     */
    private $statusApiUrl = 'https://www.ezship.com.tw/emap/ezship_request_order_status_api.jsp';

    /**
     * 格式 參數 or xml
     */
    private $useFormat = '';

    /**
     * ezship 帳號
     */
    private $suId = '';

    /**
     * 檢查ssl
     */
    private $chechSSL = 0;

    function __construct(array $config = null)
    {
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }

        if (empty($this->suId)) {
            die('配置失敗，ezShip帳號不能為空。');
        }
    }

    /**
     * 傳送
     */
    public function send($orderData = '', $returnUrl = '')
    {
        if (empty($orderData)) {
            return [
                'status' => 'error',
                'message' => '傳送失敗，訂單資訊不能為空。'
            ];
        }

        $postData = '';
        if ($this->useFormat == 'xml') {
            $data = $this->formatForXml($orderData, $returnUrl);
            $apiUrl = $this->xmlApiUrl.$data;
        } else {
            $postData = $this->formatForParameter($orderData, $returnUrl);
            $apiUrl = $this->parameterApiUrl;
        }

        //curl
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        //注意這是遞歸的，PHP將發送形如 Location:
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        //ssl 檢查
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->chechSSL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->chechSSL);
        curl_exec($ch);

        //取得響應資訊 (由於ezship是用網址get回傳因此需解析響應資訊裡的redirect_url)
        $responseUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close ($ch);

        //解析網址取得 QUERY 的部分。 eg:order_id=12&sn_id=207669587&order_status=S01&webPara=
        $parseParam = parse_url($responseUrl, PHP_URL_QUERY);

        //寫入陣列
        $parseParamArr = array();
        parse_str($parseParam, $parseParamArr);

        $returnWebPara = (isset($parseParamArr['webPara'])) ? $parseParamArr['webPara'] : '';
        if ($returnWebPara != $orderData['web_para']) {
            return [
                'status' => 'error',
                'message' => '串接ezShip物流失敗，錯誤原因:驗證失敗。'
            ];
        }

        $returnStatusCode = (isset($parseParamArr['order_status'])) ? $parseParamArr['order_status'] : '';
        if(($returnStatusCode) != 'S01') {
            return [
                'status' => 'error',
                'message' => $this->getErrorMessage($returnStatusCode)
            ];
        } else {
            return [
                'status' => 'success',
                'data' => $parseParamArr
            ];
        }
    }

    /**
     * xml版
     *
     * @param string $returnUrl
     * @param string $returnUrl
     */
    private function formatForXml($orderData, $returnUrl)
    {
        $param = '';

        $orderID = isset($orderData['number']) ? $orderData['number'] : '';
        $orderStatus = isset($orderData['ezship']['status']) ? $orderData['ezship']['status'] : '';
        $orderType = isset($orderData['ezship']['type']) ? $orderData['ezship']['type'] : '';
        $orderAmount = isset($orderData['amount']) ? $orderData['amount'] : '';

        //收件人資訊
        $rvName = isset($orderData['name']) ? $orderData['name'] : '';
        $rvEmail = isset($orderData['email']) ? $orderData['email'] : '';
        $rvMobile = isset($orderData['mobile']) ? str_replace('-', '', $orderData['mobile']) : '';
        $rvAddr = isset($orderData['address']) ? str_replace(' ', '', $orderData['address']) : '';
        $rvZip = isset($orderData['zipcode']) ? $orderData['zipcode'] : '';

        //超商資訊
        $stCate = isset($orderData['ezship']['stCate']) ? $orderData['ezship']['stCate'] : '';
        $stCode = isset($orderData['ezship']['stCode']) ? $orderData['ezship']['stCode'] : '';
        $stCateFull = $stCate.$stCode;

        $webPara = isset($orderData['web_para']) ? $orderData['web_para'] : '';

        //參數
        $param = '?web_map_xml=';
        $param .= urlencode( preg_replace('/[\n\s]+/', '',
                '<ORDER>
                   <suID>'.$this->suId.'</suID>
                   <orderID>'.$orderID.'</orderID>
                   <orderStatus>'.$orderStatus.'</orderStatus>
                   <orderType>'.$orderType.'</orderType>
                   <orderAmount>'.$orderAmount.'</orderAmount>
                   <rvName><![CDATA['.$rvName.']]></rvName>
                   <rvEmail>'.$rvEmail.'</rvEmail>
                   <rvMobile>'.$rvMobile.'</rvMobile>'));

        if ($orderData['ezship']['status'] == 'A05' || $orderData['ezship']['status'] == 'A06') {
            $param .= urlencode( preg_replace('/[\n\s]+/', '',
                    '<rvAddr><![CDATA['.$rvAddr.']]></rvAddr>
                    <rvZip>'.$rvZip.'</rvZip>'));
        } else {
            $param .= urlencode( preg_replace('/[\n\s]+/', '',
                    '<stCode>'.$stCateFull.'</stCode>'));
        }

        $param .= urlencode( preg_replace('/[\n\s]+/', '',
                '<rtURL>'.$returnUrl.'</rtURL>
                   <webPara>'.$webPara.'</webPara>
                '));

        //附加商品明細
        if(is_array($orderData['goods']) && count($orderData['goods'])>0 ){
            foreach ($orderData['goods'] as $number => $good){
                $param .= urlencode( preg_replace('/[\n\s]+/', '',
                '<Detail>
                  <prodItem>'.$number.'</prodItem>
                  <prodNo>'.$good['number'].'</prodNo>
                  <prodName><![CDATA['.$good['name'].']]></prodName>
                  <prodPrice>'.$good['price'].'</prodPrice>
                  <prodQty>'.$good['qty'].'</prodQty>
                  <prodSpec><![CDATA['.$good['spc'].']]></prodSpec>
                </Detail>'));
            }
        }

        $param .= urlencode( preg_replace('/[\n\s]+/', '','</ORDER>'));

        return $param;
    }

    /**
     * 參數版
     *
     * @param string $returnUrl
     * @param string $returnUrl
     */
    private function formatForParameter($orderData, $returnUrl)
    {
        $param = [];

        $param['su_id'] = $this->suId;

        //長度最多為10
        $param['order_id'] = isset($orderData['number']) ? $orderData['number'] : '';

        //配送方式
        $param['order_status'] = isset($orderData['ezship']['status']) ? $orderData['ezship']['status'] : '';
        if (in_array($param['order_status'], ['A05', 'A06'])) {
            //宅配
            $param['rv_zip'] = isset($orderData['zipcode']) ? $orderData['zipcode'] : '';
            $param['rv_addr'] = isset($orderData['address']) ? $orderData['address'] : '';
        } else {
            //超商
            $stCate = isset($orderData['ezship']['stCate']) ?$orderData['ezship']['stCate'] : '';
            $stCode = isset($orderData['ezship']['stCode']) ?$orderData['ezship']['stCode'] : '';
            $param['st_code'] = $stCate.$stCode;
        }

        //付款方式 1:付款 3:不付款
        $param['order_type'] = isset($orderData['ezship']['type']) ? $orderData['ezship']['type'] : '';
        $param['order_amount'] = isset($orderData['amount']) ?$orderData['amount'] : '';

        //收件人資訊
        $param['rv_name'] = isset($orderData['name']) ? $orderData['name'] : '';
        $param['rv_email'] = isset($orderData['email']) ? $orderData['email'] : '';
        $param['rv_mobile'] = isset($orderData['mobile']) ? $orderData['mobile'] : '';

        //導向位置
        $param['rtn_url'] = $returnUrl;
        $param['web_para'] = isset($orderData['web_para']) ? $orderData['web_para'] : '';

        return urldecode(http_build_query($param));
    }

    /**
     * 取得電子地圖
     *
     * @param string $returnUrl
     * @param string $webPara
     */
    public function getMap($returnUrl = '', $number = '', $webPara = '')
    {
        if (empty($returnUrl)) {
            die('請輸入有效的回傳位置。');
        }

        $html ="
        <form action='$this->mapApiUrl' name='simulation_to' method='post'>
            <input type='hidden' name='suID' value='$this->suId' />
            <input type='hidden' name='processID' value='$number' />
            <input type='hidden' name='stCate' value='' />
            <input type='hidden' name='stCode' value='' />
            <input type='hidden' name='rtURL' value='$returnUrl' />
            <input type='hidden' name='webPara' value='$webPara' />
        </form>
        <script>simulation_to.submit();</script>";

        echo $html;
    }


    /**
     * 錯誤訊息取得
     *
     * @param string $code
     */
    private function getErrorMessage($code)
    {
        switch ($code) {
            case 'E00':
                $errorMessage = '串接ezShip物流失敗，錯誤原因:參數傳遞內容有誤或欄位短缺。';
                break;

            case 'E01':
                $errorMessage = '串接ezShip物流失敗，錯誤原因:串接帳號不存在。';
                break;

            case 'E02':
                $errorMessage = '串接ezShip物流失敗，錯誤原因:帳號無建立取貨付款權限或無網站串接權限或無ezShip宅配權限 。';
                break;

            case 'E03':
                $errorMessage = '串接ezShip物流失敗，錯誤原因:帳號無可用輕鬆袋或迷你袋。';
                break;

            case 'E04':
                $errorMessage = '串接ezShip物流失敗，錯誤原因:取件門市有誤。';
                break;

            case 'E05':
                $errorMessage = '串接ezShip物流失敗，錯誤原因:訂單金額只能介於10~8000之間或無須付款訂單價值超過0~2000(一般)/4000(商業)';
                break;

            case 'E06':
                $errorMessage = '串接ezShip物流失敗，錯誤原因:電子郵件信箱有誤。';
                break;

            case 'E07':
                $errorMessage = '串接ezShip物流失敗，錯誤原因:收件人電話有誤。';
                break;

            case 'E08':
                $errorMessage = '串接ezShip物流失敗，錯誤原因:狀態有誤。';
                break;

            case 'E09':
                $errorMessage = '串接ezShip物流失敗，錯誤原因:型態有誤。';
                break;

            case 'E10':
                $errorMessage = '串接ezShip物流失敗，錯誤原因:收件人有誤。';
                break;

            case 'E11':
                $errorMessage = '串接ezShip物流失敗，錯誤原因:配送地址有誤。';
                break;

            case 'E98':
                $errorMessage = '串接ezShip物流失敗，錯誤原因:系統發生錯誤無法載入。';
                break;

            case 'E99':
                $errorMessage = '串接ezShip物流失敗，錯誤原因:系統錯誤。';
                break;

            default:
                $errorMessage = '串接ezShip物流失敗，錯誤原因:串接流程有誤。';
                break;
        }

        return $errorMessage;
    }

    /**
     * 訂單狀態查詢
     *
     * @param string $orderNumber
     * @param string $returnUrl
     */
    public function getStatus($orderNumber = '', $webPara = '', $returnUrl = '')
    {
        if (empty($orderNumber)) {
            return [
                'status' => 'error',
                'message' => '傳送失敗，訂單編號不能為空。'
            ];
        }

        $postData = [
            'su_id' => $this->suId,
            'sn_id' => $orderNumber,
            'rtn_url' => $returnUrl,
            'web_para' => $webPara
        ];
        $postData = urldecode(http_build_query($postData));

        //curl
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->statusApiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        //注意這是遞歸的，PHP將發送形如 Location:
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        //ssl 檢查
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->chechSSL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->chechSSL);
        curl_exec($ch);

        //取得響應資訊 (由於ezship是用網址get回傳因此需解析響應資訊裡的redirect_url)
        $responseUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close ($ch);

        //解析網址取得 QUERY 的部分。 eg:order_id=12&sn_id=207669587&order_status=S01&webPara=
        $parseParam = parse_url($responseUrl, PHP_URL_QUERY);

        $parseParamArr = [];
        parse_str($parseParam, $parseParamArr);

        $returnWebPara = (isset($parseParamArr['web_para'])) ? $parseParamArr['web_para'] : '';
        if ($returnWebPara != 'test') {
            return [
                'status' => 'error',
                'message' => '串接ezShip物流失敗，錯誤原因:驗證失敗。'
            ];
        }

        $returnStatus = (isset($parseParamArr['order_status'])) ? $parseParamArr['order_status'] : '';
        return [
            'status' => 'success',
            'message' => $this->getStatusMessage($returnStatus)
        ];
    }

    /**
     * 狀態回傳內容
     *
     * @param string $code
     */
    private function getStatusMessage($code)
    {
        switch ($code) {
            case 'S01':
                $statusMessage = '尚未寄件或尚未收到超商總公司提供的寄件訊息。';
                break;

            case 'S02':
                $statusMessage = '運往取件門市途中。';
                break;

            case 'S03':
                $statusMessage = '已送達取件門市。';
                break;

            case 'S04':
                $statusMessage = '已完成取貨。';
                break;

            case 'S05':
                $statusMessage = '退貨 (包含：已退回物流中心 / 再寄一次給取件人 / 退回給寄件人)。';
                break;

            case 'S06':
                $statusMessage = '配送異常 (包含：刪單 / 門市閉店 / 貨故)';
                break;

            case 'E00':
                $statusMessage = '串接ezShip物流失敗，錯誤原因:參數傳遞內容有誤或欄位短缺。';
                break;

            case 'E01':
                $statusMessage = '串接ezShip物流失敗，錯誤原因:ezship帳號不存在。';
                break;

            case 'E02':
                $statusMessage = '串接ezShip物流失敗，錯誤原因:ezship帳號無網站串接權限。';
                break;

            case 'E03':
                $statusMessage = '串接ezShip物流失敗，錯誤原因:店到店編號有誤。';
                break;

            case 'E04':
                $statusMessage = '串接ezShip物流失敗，錯誤原因:ezShip帳號與訂單店到店編號無法對應';
                break;

            case 'E99':
                $statusMessage = '串接ezShip物流失敗，錯誤原因:系統錯誤。';
                break;

            default:
                $statusMessage = '串接ezShip物流失敗，錯誤原因:串接流程有誤。';
                break;
        }

        return $statusMessage;
    }
}
