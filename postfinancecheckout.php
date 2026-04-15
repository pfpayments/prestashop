<?php

/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2026 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

if (!defined('_PS_VERSION_')) {
    exit();
}

use PrestaShop\PrestaShop\Core\Domain\Order\CancellationActionType;

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'postfinancecheckout_autoloader.php');
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'postfinancecheckout-sdk' . DIRECTORY_SEPARATOR .
    'autoload.php');
class PostFinanceCheckout extends PaymentModule
{

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->name = 'postfinancecheckout';
        $this->tab = 'payments_gateways';
        $this->author = 'wallee AG';
        $this->bootstrap = true;
        $this->need_instance = 0;
        $this->version = '2.0.6';
        $this->displayName = 'PostFinance Checkout';
        $this->description = $this->l('This PrestaShop module enables to process payments with %s.');
        $this->description = sprintf($this->description, 'PostFinance Checkout');
        $this->module_key = 'PrestaShop_ProductKey_V8';
        $this->ps_versions_compliancy = array(
            'min' => '8',
            'max' => _PS_VERSION_
        );
        parent::__construct();
        $this->confirmUninstall = sprintf(
            $this->l('Are you sure you want to uninstall the %s module?', 'abstractmodule'),
            'PostFinance Checkout'
        );

        // Remove Fee Item
        if (isset($this->context->cart) && Validate::isLoadedObject($this->context->cart)) {
            PostFinanceCheckoutFeehelper::removeFeeSurchargeProductsFromCart($this->context->cart);
        }
        if (!empty($this->context->cookie->pfc_error)) {
            $errors = $this->context->cookie->pfc_error;
            if (is_string($errors)) {
                $this->context->controller->errors[] = $errors;
            } elseif (is_array($errors)) {
                foreach ($errors as $error) {
                    $this->context->controller->errors[] = $error;
                }
            }
            unset($_SERVER['HTTP_REFERER']); // To disable the back button in the error message
            $this->context->cookie->pfc_error = null;
        }
    }

    public function addError($error)
    {
        $this->_errors[] = $error;
    }

    public function getContext()
    {
        return $this->context;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function install()
    {
        if (!PostFinanceCheckoutBasemodule::checkRequirements($this)) {
            return false;
        }
        if (!parent::install()) {
            return false;
        }
        return PostFinanceCheckoutBasemodule::install($this);
    }

    public function uninstall()
    {
        return parent::uninstall() && PostFinanceCheckoutBasemodule::uninstall($this);
    }

    public function upgrade($version)
    {
        return true;
    }

    public function installHooks()
    {
        return PostFinanceCheckoutBasemodule::installHooks($this) && $this->registerHook('paymentOptions') &&
            $this->registerHook('actionFrontControllerSetMedia') &&
            $this->registerHook('actionValidateStepComplete') &&
            $this->registerHook('actionObjectAddressAddAfter');
    }

    public function getBackendControllers()
    {
        return array(
            'AdminPostFinanceCheckoutMethodSettings' => array(
                'parentId' => Tab::getIdFromClassName('AdminParentPayment'),
                'name' => 'PostFinance Checkout ' . $this->l('Payment Methods')
            ),
            'AdminPostFinanceCheckoutDocuments' => array(
                'parentId' => -1, // No Tab in navigation
                'name' => 'PostFinance Checkout ' . $this->l('Documents')
            ),
            'AdminPostFinanceCheckoutOrder' => array(
                'parentId' => -1, // No Tab in navigation
                'name' => 'PostFinance Checkout ' . $this->l('Order Management')
            )
        );
    }

    public function installConfigurationValues()
    {
        return PostFinanceCheckoutBasemodule::installConfigurationValues();
    }

    public function uninstallConfigurationValues()
    {
        return PostFinanceCheckoutBasemodule::uninstallConfigurationValues();
    }

    public function getContent()
    {
        $output = PostFinanceCheckoutBasemodule::handleSaveAll($this);
        $output .= PostFinanceCheckoutBasemodule::handleSaveApplication($this);
        $output .= PostFinanceCheckoutBasemodule::handleSaveEmail($this);
        $output .= PostFinanceCheckoutBasemodule::handleSaveIntegration($this);
        $output .= PostFinanceCheckoutBasemodule::handleSaveCartRecreation($this);
        $output .= PostFinanceCheckoutBasemodule::handleSaveFeeItem($this);
        $output .= PostFinanceCheckoutBasemodule::handleSaveDownload($this);
        $output .= PostFinanceCheckoutBasemodule::handleSaveSpaceViewId($this);
        $output .= PostFinanceCheckoutBasemodule::handleSaveOrderStatus($this);
        $output .= PostFinanceCheckoutBasemodule::displayHelpButtons($this);
        return $output . PostFinanceCheckoutBasemodule::displayForm($this);
    }

    public function getConfigurationForms()
    {
        return array(
            PostFinanceCheckoutBasemodule::getEmailForm($this),
            PostFinanceCheckoutBasemodule::getIntegrationForm($this),
            PostFinanceCheckoutBasemodule::getCartRecreationForm($this),
            PostFinanceCheckoutBasemodule::getFeeForm($this),
            PostFinanceCheckoutBasemodule::getDocumentForm($this),
            PostFinanceCheckoutBasemodule::getSpaceViewIdForm($this),
            PostFinanceCheckoutBasemodule::getOrderStatusForm($this)
        );
    }

    public function getConfigurationValues()
    {
        return array_merge(
            PostFinanceCheckoutBasemodule::getApplicationConfigValues($this),
            PostFinanceCheckoutBasemodule::getEmailConfigValues($this),
            PostFinanceCheckoutBasemodule::getIntegrationConfigValues($this),
            PostFinanceCheckoutBasemodule::getCartRecreationConfigValues($this),
            PostFinanceCheckoutBasemodule::getFeeItemConfigValues($this),
            PostFinanceCheckoutBasemodule::getDownloadConfigValues($this),
            PostFinanceCheckoutBasemodule::getSpaceViewIdConfigValues($this),
            PostFinanceCheckoutBasemodule::getOrderStatusConfigValues($this)
        );
    }

    public function getConfigurationKeys()
    {
        return PostFinanceCheckoutBasemodule::getConfigurationKeys();
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!isset($params['cart']) || !($params['cart'] instanceof Cart)) {
            return;
        }
        $cart = $params['cart'];
        try {
            $transactionService = PostFinanceCheckoutServiceTransaction::instance();
            $transaction = $transactionService->getTransactionFromCart($cart);
            $possiblePaymentMethods = $transactionService->getPossiblePaymentMethods($cart, $transaction);
        } catch (PostFinanceCheckoutExceptionInvalidtransactionamount $e) {
            PrestaShopLogger::addLog($e->getMessage() . " CartId: " . $cart->id, 2, null, 'PostFinanceCheckout');
            $paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $paymentOption->setCallToActionText(
                $this->l('There is an issue with your cart, some payment methods are not available.')
            );
            $paymentOption->setAdditionalInformation(
                $this->context->smarty->fetch(
                    'module:postfinancecheckout/views/templates/front/hook/amount_error.tpl'
                )
            );
            $paymentOption->setForm(
                $this->context->smarty->fetch(
                    'module:postfinancecheckout/views/templates/front/hook/amount_error_form.tpl'
                )
            );
            $paymentOption->setModuleName($this->name . "-error");
            return array(
                $paymentOption
            );
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage() . " CartId: " . $cart->id, 1, null, 'PostFinanceCheckout');
            return array();
        }
        $shopId = $cart->id_shop;
        $language = Context::getContext()->language->language_code;
        $methods = $this->filterShopMethodConfigurations($shopId, $possiblePaymentMethods);
        $result = array();

        $this->context->smarty->registerPlugin(
            'function',
            'postfinancecheckout_clean_html',
            array(
                'PostFinanceCheckoutSmartyfunctions',
                'cleanHtml'
            )
        );

        foreach (PostFinanceCheckoutHelper::sortMethodConfiguration($methods) as $methodConfiguration) {
            $parameters = PostFinanceCheckoutBasemodule::getParametersFromMethodConfiguration($this, $methodConfiguration, $cart, $shopId, $language);
            $parameters['priceDisplayTax'] = Group::getPriceDisplayMethod(Group::getCurrent()->id);
            $parameters['orderUrl'] = $this->context->link->getModuleLink(
                'postfinancecheckout',
                'order',
                array(),
                true
            );
            $this->context->smarty->assign($parameters);
            $paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $paymentOption->setCallToActionText($parameters['name']);
            $paymentOption->setLogo($parameters['image']);
            $paymentOption->setAction($parameters['link']);
            $paymentOption->setAdditionalInformation(
                $this->context->smarty->fetch(
                    'module:postfinancecheckout/views/templates/front/hook/payment_additional.tpl'
                )
            );
            $paymentOption->setForm(
                $this->context->smarty->fetch(
                    'module:postfinancecheckout/views/templates/front/hook/payment_form.tpl'
                )
            );
            $paymentOption->setModuleName($this->name);
            $result[] = $paymentOption;
        }
        return $result;
    }

    /**
     * Filters configured method entities for the current shop and the available SDK payment methods.
     *
     * @param int $shopId
     * @param \PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration[] $possiblePaymentMethods
     * @return PostFinanceCheckoutModelMethodconfiguration[]
     */
    protected function filterShopMethodConfigurations($shopId, array $possiblePaymentMethods)
    {
        $configured = PostFinanceCheckoutModelMethodconfiguration::loadValidForShop($shopId);
        if (empty($configured) || empty($possiblePaymentMethods)) {
            return array();
        }

        $bySpaceAndConfiguration = array();
        foreach ($configured as $methodConfiguration) {
            $spaceId = $methodConfiguration->getSpaceId();
            if (! isset($bySpaceAndConfiguration[$spaceId])) {
                $bySpaceAndConfiguration[$spaceId] = array();
            }
            $bySpaceAndConfiguration[$spaceId][$methodConfiguration->getConfigurationId()] = $methodConfiguration;
        }

        $result = array();
        foreach ($possiblePaymentMethods as $possible) {
            $spaceId = $possible->getSpaceId();
            $configurationId = $possible->getId();
            if (isset($bySpaceAndConfiguration[$spaceId][$configurationId])) {
                $methodConfiguration = $bySpaceAndConfiguration[$spaceId][$configurationId];
                if ($methodConfiguration->isActive()) {
                    $result[] = $methodConfiguration;
                }
            }
        }

        return $result;
    }

    public function hookActionFrontControllerSetMedia()
    {
        $controller = $this->context->controller;

        if (!$controller) {
            return;
        }

        $phpSelf = $controller->php_self;
        if ($phpSelf === 'order' || $phpSelf === 'cart') {

            // Ensure device ID exists
            if (empty($this->context->cookie->pfc_device_id)) {
                $this->context->cookie->pfc_device_id = PostFinanceCheckoutHelper::generateUUID();
            }

            $deviceId = $this->context->cookie->pfc_device_id;

            $scriptUrl = PostFinanceCheckoutHelper::getBaseGatewayUrl() .
                '/s/' . Configuration::get(PostFinanceCheckoutBasemodule::CK_SPACE_ID) .
                '/payment/device.js?sessionIdentifier=' . $deviceId;

            $controller->registerJavascript(
                'postfinancecheckout-device-identifier',
                $scriptUrl,
                [
                'server' => 'remote',
                'attributes' => 'async'
                ]
            );
        }

        /**
         * ORDER PAGE ONLY
         * Add checkout JS/CSS + iframe handler
         */
        if ($phpSelf === 'order') {

            // checkout styles
            $controller->registerStylesheet(
                'postfinancecheckout-checkout-css',
                'modules/' . $this->name . '/views/css/frontend/checkout.css'
            );

            // checkout JS
            $controller->registerJavascript(
                'postfinancecheckout-checkout-js',
                'modules/' . $this->name . '/views/js/frontend/checkout.js'
            );

            // define global JS variables
            Media::addJsDef([
                'postFinanceCheckoutCheckoutUrl' => $this->context->link->getModuleLink(
                'postfinancecheckout',
                'checkout',
                [],
                true
                ),
                'postfinancecheckoutMsgJsonError' => $this->l(
                'The server experienced an unexpected error, you may try again or try a different payment method.'
                )
            ]);

            // Iframe handler JS (only when integration = iframe)
            $cart = $this->context->cart;

            if ($cart && Validate::isLoadedObject($cart)) {
                try {
                    // Get integration type from configuration
                    // 0 = iframe
                    // 1 = payment page
                    $integrationType = (int) Configuration::get(PostFinanceCheckoutBasemodule::CK_INTEGRATION);

                    // Only load JS when NOT payment page
                    if ($integrationType !== Configuration::get(PostFinanceCheckoutBasemodule::CK_INTEGRATION_TYPE_PAYMENT_PAGE)) {

                        $jsUrl = PostFinanceCheckoutServiceTransaction::instance()
                            ->getJavascriptUrl($cart);

                        $this->context->controller->registerJavascript(
                            'postfinancecheckout-iframe-handler',
                            $jsUrl,
                            [
                            'server' => 'remote',
                            'priority' => 45,
                            'attributes' => 'id="postfinancecheckout-iframe-handler"'
                            ]
                        );
                    }

                } catch (Exception $e) {
                    // same behavior: silently ignore
                }
            }
        }

        /**
         * ORDER-DETAIL PAGE
         */
        if ($phpSelf === 'order-detail') {
            $controller->registerJavascript(
                'postfinancecheckout-orderdetail-js',
                'modules/' . $this->name . '/views/js/frontend/orderdetail.js'
            );
        }
    }

    public function hookActionObjectAddressAddAfter($params)
    {
        $this->processAddressChange(isset($params['object']) ? $params['object'] : null);
    }

    public function hookActionValidateStepComplete($params)
    {
        if (isset($params['step_name']) && $params['step_name'] === 'addresses') {
            $this->processAddressChange(null);
        }
    }

    /**
     * Refreshes the pending transaction when the checkout address is created/selected.
     *
     * @param Address|null $address
     */
    private function processAddressChange($address = null)
    {
        $cart = $this->context->cart;
        if (!$cart || !Validate::isLoadedObject($cart)) {
            return;
        }

        try {
            PostFinanceCheckoutServiceTransaction::instance()->refreshTransactionFromCart($cart);
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'PostFinanceCheckout address refresh failed: ' . $e->getMessage(),
                2,
                null,
                $this->name
            );
        }
    }


    public function hookActionAdminControllerSetMedia($arr)
    {
        PostFinanceCheckoutBasemodule::hookActionAdminControllerSetMedia($this, $arr);
        $this->context->controller->addCSS(__PS_BASE_URI__ . 'modules/' . $this->name . '/views/css/admin/general.css');
    }

    public function hasBackendControllerDeleteAccess(AdminController $backendController)
    {
        return $backendController->access('delete');
    }

    public function hasBackendControllerEditAccess(AdminController $backendController)
    {
        return $backendController->access('edit');
    }

    /**
     * Show the manual task in the admin bar.
     * The output is moved with javascript to the correct place as better hook is missing.
     *
     * @return string
     */
    public function hookDisplayAdminAfterHeader()
    {
        $result = PostFinanceCheckoutBasemodule::hookDisplayAdminAfterHeader($this);
        return $result;
    }

    public function hookPostFinanceCheckoutSettingsChanged($params)
    {
        return PostFinanceCheckoutBasemodule::hookPostFinanceCheckoutSettingsChanged($this, $params);
    }

    public function hookActionMailSend($data)
    {
        return PostFinanceCheckoutBasemodule::hookActionMailSend($this, $data);
    }

    public function validateOrder(
        $id_cart,
        $id_order_state,
        $amount_paid,
        $payment_method = 'Unknown',
        $message = null,
        $extra_vars = array(),
        $currency_special = null,
        $dont_touch_amount = false,
        $secure_key = false,
        Shop $shop = null,
        $order_reference = null
    ) {
        PostFinanceCheckoutBasemodule::validateOrder($this, $id_cart, $id_order_state, $amount_paid, $payment_method, $message, $extra_vars, $currency_special, $dont_touch_amount, $secure_key, $shop, $order_reference);
    }

    public function validateOrderParent(
        $id_cart,
        $id_order_state,
        $amount_paid,
        $payment_method = 'Unknown',
        $message = null,
        $extra_vars = array(),
        $currency_special = null,
        $dont_touch_amount = false,
        $secure_key = false,
        Shop $shop = null,
        $order_reference = null
    ) {
        parent::validateOrder($id_cart, $id_order_state, $amount_paid, $payment_method, $message, $extra_vars, $currency_special, $dont_touch_amount, $secure_key, $shop, $order_reference);
    }

    public function hookDisplayOrderDetail($params)
    {
        return PostFinanceCheckoutBasemodule::hookDisplayOrderDetail($this, $params);
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        PostFinanceCheckoutBasemodule::hookDisplayBackOfficeHeader($this, $params);
    }

    public function hookDisplayAdminOrderLeft($params)
    {
        return PostFinanceCheckoutBasemodule::hookDisplayAdminOrderLeft($this, $params);
    }

    public function hookDisplayAdminOrderTabOrder($params)
    {
        return PostFinanceCheckoutBasemodule::hookDisplayAdminOrderTabOrder($this, $params);
    }

    public function hookDisplayAdminOrderMain($params)
    {
        return PostFinanceCheckoutBasemodule::hookDisplayAdminOrderMain($this, $params);
    }

    public function hookActionOrderSlipAdd($params)
    {
        $refundParameters = Tools::getAllValues();

        $order = $params['order'];

        if (!Validate::isLoadedObject($order) || $order->module != $this->name) {
            $idOrder = Tools::getValue('id_order');
            if (!$idOrder) {
                $order = $params['order'];
                $idOrder = (int)$order->id;
            }
            $order = new Order((int) $idOrder);
            if (! Validate::isLoadedObject($order) || $order->module != $module->name) {
                return;
            }
        }

        $strategy = PostFinanceCheckoutBackendStrategyprovider::getStrategy();

        if ($strategy->isVoucherOnlyPostFinanceCheckout($order, $refundParameters)) {
            return;
        }

        // need to manually set this here as it's expected downstream
        $refundParameters['partialRefund'] = true;

        $backendController = Context::getContext()->controller;
        $editAccess = 0;

        $access = Profile::getProfileAccess(
            Context::getContext()->employee->id_profile,
            (int) Tab::getIdFromClassName('AdminOrders')
        );
        $editAccess = isset($access['edit']) && $access['edit'] == 1;

        if ($editAccess) {
            try {
                $parsedData = $strategy->simplifiedRefund($refundParameters);
                PostFinanceCheckoutServiceRefund::instance()->executeRefund($order, $parsedData);
            } catch (Exception $e) {
                $backendController->errors[] = PostFinanceCheckoutHelper::cleanExceptionMessage($e->getMessage());
            }
        } else {
            $backendController->errors[] = Tools::displayError('You do not have permission to delete this.');
        }
    }

    public function hookDisplayAdminOrderTabLink($params)
    {
        return PostFinanceCheckoutBasemodule::hookDisplayAdminOrderTabLink($this, $params);
    }

    public function hookDisplayAdminOrderContentOrder($params)
    {
        return PostFinanceCheckoutBasemodule::hookDisplayAdminOrderContentOrder($this, $params);
    }

    public function hookDisplayAdminOrderTabContent($params)
    {
        return PostFinanceCheckoutBasemodule::hookDisplayAdminOrderTabContent($this, $params);
    }

    public function hookDisplayAdminOrder($params)
    {
        return PostFinanceCheckoutBasemodule::hookDisplayAdminOrder($this, $params);
    }

    public function hookActionAdminOrdersControllerBefore($params)
    {
        return PostFinanceCheckoutBasemodule::hookActionAdminOrdersControllerBefore($this, $params);
    }

    public function hookActionObjectOrderPaymentAddBefore($params)
    {
        PostFinanceCheckoutBasemodule::hookActionObjectOrderPaymentAddBefore($this, $params);
    }

    public function hookActionOrderEdited($params)
    {
        PostFinanceCheckoutBasemodule::hookActionOrderEdited($this, $params);
    }

    public function hookActionOrderGridDefinitionModifier($params)
    {
        PostFinanceCheckoutBasemodule::hookActionOrderGridDefinitionModifier($this, $params);
    }

    public function hookActionOrderGridQueryBuilderModifier($params)
    {
        PostFinanceCheckoutBasemodule::hookActionOrderGridQueryBuilderModifier($this, $params);
    }

    public function hookActionProductCancel($params)
    {
        if ($params['action'] === CancellationActionType::PARTIAL_REFUND) {
            $idOrder = Tools::getValue('id_order');
            $refundParameters = Tools::getAllValues();

            $order = $params['order'];

            if (!Validate::isLoadedObject($order) || $order->module != $this->name) {
                return;
            }

            $strategy = PostFinanceCheckoutBackendStrategyprovider::getStrategy();
            if ($strategy->isVoucherOnlyPostFinanceCheckout($order, $refundParameters)) {
                return;
            }

            // need to manually set this here as it's expected downstream
            $refundParameters['partialRefund'] = true;

            $backendController = Context::getContext()->controller;
            $editAccess = 0;

            $access = Profile::getProfileAccess(
                Context::getContext()->employee->id_profile,
                (int) Tab::getIdFromClassName('AdminOrders')
            );
            $editAccess = isset($access['edit']) && $access['edit'] == 1;

            if ($editAccess) {
                try {
                    $parsedData = $strategy->simplifiedRefund($refundParameters);
                    PostFinanceCheckoutServiceRefund::instance()->executeRefund($order, $parsedData);
                } catch (Exception $e) {
                    $backendController->errors[] = PostFinanceCheckoutHelper::cleanExceptionMessage($e->getMessage());
                }
            } else {
                $backendController->errors[] = Tools::displayError('You do not have permission to delete this.');
            }
        }
    }
}
