<?php
/**
 * Copyright ©O2TI Soluções Web  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace O2TI\MoipExtendedSplit\Plugin\Gateway\Request;

use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\ScopeInterface;
use Moip\Magento2\Gateway\Config\Config;
use Moip\Magento2\Gateway\Config\ConfigCc;
use Moip\Magento2\Gateway\Data\Order\OrderAdapterFactory;
use Moip\Magento2\Gateway\Request\SellerDataRequest;
use Moip\Magento2\Gateway\SubjectReader;

class NewSellerData 
{
    const RECEIVERS_FEE_PAYOR = "feePayor";

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var OrderAdapterFactory
     */
    private $orderAdapterFactory;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var configCc
     */
    private $configCc;

    /**
     * @var priceHelper
     */
    private $priceHelper;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param SubjectReader       $subjectReader
     * @param OrderAdapterFactory $orderAdapterFactory
     * @param Config              $Config
     * @param ConfigCc            $ConfigCc
     * @param CheckoutHelper      $checkoutHelper
     */
    public function __construct(
        SubjectReader $subjectReader,
        OrderAdapterFactory $orderAdapterFactory,
        Config $config,
        ConfigCc $configCc,
        PriceHelper $checkoutHelper,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->subjectReader = $subjectReader;
        $this->orderAdapterFactory = $orderAdapterFactory;
        $this->config = $config;
        $this->configCc = $configCc;
        $this->priceHelper = $checkoutHelper;
        $this->scopeConfig = $scopeConfig;
    }

    public function aroundBuild(
        SellerDataRequest $subject,
        \Closure $proceed,
        $buildSubject
    ) {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $result = [];

        $orderAdapter = $this->orderAdapterFactory->create(
            ['order' => $payment->getOrder()]
        );

        $order = $paymentDO->getOrder();

        $storeId = $order->getStoreId();
        
        if(!$this->getConfigValueExtended('use_split', $storeId)) {
            return $result;
        }

        $grandTotal = $order->getGrandTotalAmount();

        $secondaryMPA = $this->getConfigValueExtended('secondary_mpa', $storeId);
        $secondaryFeePayor = (bool)$this->getConfigValueExtended('fee_payor', $storeId);

        $result[SellerDataRequest::RECEIVERS][] = [
            SellerDataRequest::RECEIVERS_MOIP_ACCOUNT => [
                SellerDataRequest::RECEIVERS_MOIP_ACCOUNT_ID => $secondaryMPA,
            ],
            SellerDataRequest::RECEIVERS_TYPE   => SellerDataRequest::RECEIVERS_TYPE_SECONDARY,
            SellerDataRequest::RECEIVERS_AMOUNT => [
                SellerDataRequest::RECEIVERS_TYPE_FIXED => $this->config->formatPrice($grandTotal),
            ],
            self::RECEIVERS_FEE_PAYOR => $secondaryFeePayor
        ];

        return $result;
    }

    /**
     * Gets the  Config Value.
     *
     * @param string   $typePattern
     * @param string   $field
     * @param int|null $storeId
     *
     * @return string
     */
    public function getConfigValueExtended($field, $storeId = null): ?string
    {
        $pathPattern = 'o2ti/moip_extended/%s';

        return $this->scopeConfig->getValue(
            sprintf($pathPattern, $field),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}

