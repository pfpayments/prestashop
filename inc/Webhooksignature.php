<?php
/**
 * Class Webhooksignature
 *
 * This class is responsible for fetching webhook listeners and ensuring that
 * payload signature and state are enabled for active webhook listeners.
 *
 * The `run` method iterates through all shops, retrieves the active webhook listeners
 * for each shop's configured space ID, and updates the listeners to enable payload
 * signature and state if not already enabled.
 */
class Webhooksignature
{
    public static function run()
    {
        //set listener service
        $listenerService = new \PostFinanceCheckout\Sdk\Service\WebhookListenerService(
            PostFinanceCheckoutHelper::getApiClient()
        );

        foreach (Shop::getShops(true, null, true) as $shopId) {
            $spaceId = Configuration::get(
                PostFinanceCheckoutBasemodule::CK_SPACE_ID,
                null,
                null,
                $shopId
            );

            if (!$spaceId) {
                continue;
            }

            $query = new \PostFinanceCheckout\Sdk\Model\EntityQuery();

            $filter = new \PostFinanceCheckout\Sdk\Model\EntityQueryFilter();
            $filter->setType(\PostFinanceCheckout\Sdk\Model\EntityQueryFilterType::LEAF);
            $filter->setFieldName('state');
            $filter->setOperator('EQUALS');
            $filter->setValue(\PostFinanceCheckout\Sdk\Model\CreationEntityState::ACTIVE);

            $query->setFilter($filter);

            $listeners = $listenerService->search($spaceId, $query);

            foreach ($listeners as $listener) {
                if ($listener->getEnablePayloadSignatureAndState()) {
                    continue;
                }

                $update = new \PostFinanceCheckout\Sdk\Model\WebhookListenerUpdate();
                $update->setId($listener->getId());
                $update->setVersion($listener->getVersion());
                $update->setEnablePayloadSignatureAndState(true);

                $listenerService->update($spaceId, $update);
            }
        }
        return true;
    }
}
