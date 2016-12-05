<?php

/**
 * ICEPAY Magento payment module
 *
 * @license   see LICENSE.md
 * @source    https://github.com/ICEPAYdev/Magento
 * @copyright Copyright (c) 2016 ICEPAY B.V.
 *
 * Plugin Name: ICEPAY Payment Module
 * Plugin URI: https://icepay.com/webshop-modules/advanced-online-payment-module-magento/
 * Description: ICEPAY Payment Module for Magento
 * Author: ICEPAY
 * Author URI: https://icepay.com
 * Version: 1.2.12
 * License: http://www.gnu.org/licenses/gpl-3.0.html  GNU GENERAL PUBLIC LICENSE
 */

class Icepay_IceCore_Model_Icepay_Result
{

    protected $sqlModel;
    protected $data;

    public function __construct()
    {
        $this->sqlModel = Mage::getModel('icecore/mysql4_iceCore');
        $this->data = new stdClass();
    }

    public function handle(array $_vars)
    {

        if (count($_vars) == 0)
            die("ICEPAY result page installed correctly.");
        if (!$_vars['OrderID'])
            die("No orderID found");

        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
            Mage::helper("icecore")->log("ICEPAY Result: invalid request method");
            Mage::app()->getResponse()
                ->setHeader('HTTP/1.1', '403 Forbidden')
                ->sendResponse();
            return false;
        }

        Mage::helper("icecore")->log(sprintf("Page data: %s", serialize($_GET)));

        $this->parseGetRequest();

        if ($this->generateChecksumForPage() != $this->data->checksum) {
            Mage::helper("icecore")->log("ICEPAY Result: checksum does not match");
            Mage::app()->getResponse()
                ->setHeader('HTTP/1.1','403 Forbidden')
                ->sendResponse();
            exit;
        }

        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId(($_vars['OrderID'] == "DUMMY") ? $_vars['Reference'] : $_vars['OrderID'])
            ->addStatusHistoryComment(sprintf(Mage::helper("icecore")->__("Customer returned with status: %s"), $_vars['StatusCode']))
            ->save();

        switch (strtoupper($_vars['Status'])) {
            case "ERR":
                $quoteID = Mage::getSingleton('checkout/session')->getQuoteId();
                Mage::getSingleton('core/session')->setData('ic_quoteid', $quoteID);
                Mage::getSingleton('core/session')->setData('ic_quotedate', date("Y-m-d H:i:s"));

                $msg = sprintf(Mage::helper("icecore")->__("The payment provider has returned the following error message: %s"), Mage::helper("icecore")->__($_vars['Status'] . ": " . $_vars['StatusCode']));
                $url = 'checkout/cart';
                Mage::getSingleton('checkout/session')->addError($msg);
                break;
            case "OK":
            case "OPEN":
            default:
                Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
                $url = 'checkout/onepage/success';
        };

        /* Redirect based on store */
        Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl($url));
        $url = Mage::app()->getStore($order->getStoreId())->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, true) . $url;
        Mage::app()->getFrontController()->getResponse()->setRedirect($url);

    }

    private function parseGetRequest()
    {
        $this->data->status = (isset($_GET['Status'])) ? $_GET['Status'] : "";
        $this->data->statusCode = (isset($_GET['StatusCode'])) ? $_GET['StatusCode'] : "";
        $this->data->merchant = (isset($_GET['Merchant'])) ? $_GET['Merchant'] : "";
        $this->data->orderID = (isset($_GET['OrderID'])) ? $_GET['OrderID'] : "";
        $this->data->paymentID = (isset($_GET['PaymentID'])) ? $_GET['PaymentID'] : "";
        $this->data->reference = (isset($_GET['Reference'])) ? $_GET['Reference'] : "";
        $this->data->transactionID = (isset($_GET['TransactionID'])) ? $_GET['TransactionID'] : "";
        $this->data->checksum = (isset($_GET['Checksum'])) ? $_GET['Checksum'] : "";
    }


    /**
     * Return the result page checksum
     * @access protected
     * @return string SHA1 hash
     */
    protected function generateChecksumForPage()
    {
        return sha1(
            sprintf("%s|%s|%s|%s|%s|%s|%s|%s",
                Mage::helper('icecore')->getConfig(Icepay_IceCore_Model_Config::SECRETCODE),
                Mage::helper('icecore')->getConfig(Icepay_IceCore_Model_Config::MERCHANTID),
                $this->data->status,
                $this->data->statusCode,
                $this->data->orderID,
                $this->data->paymentID,
                $this->data->reference,
                $this->data->transactionID
            )
        );
    }

}