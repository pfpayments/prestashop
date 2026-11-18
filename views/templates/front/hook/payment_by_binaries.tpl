{*
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2026 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
{foreach from=$postfinancecheckoutBinaryButtons item="button"}
  <section class="js-payment-binary js-payment-{$button.moduleName|escape:'html':'UTF-8'} disabled">
    <button
      type="button"
      class="btn btn-primary postfinancecheckout-pay-button"
      data-method-id="{$button.methodId|escape:'html':'UTF-8'}"
      disabled="disabled"
    >
      {l s='Pay with %s' sprintf=[$button.name] mod='postfinancecheckout'}
    </button>
  </section>
{/foreach}
