<?php

namespace Crm\ProductsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\UsersModule\Repository\UserMetaRepository;

class AvgProductsPaymentWidget extends BaseWidget
{
    private $templateName = 'avg_products_payment_widget.latte';

    private $userMetaRepository;

    public function __construct(WidgetManager $widgetManager, UserMetaRepository $userMetaRepository)
    {
        parent::__construct($widgetManager);
        $this->userMetaRepository = $userMetaRepository;
    }

    public function identifier()
    {
        return 'avgproductspaymentwidget';
    }

    public function render(array $usersIds)
    {
        if (count($usersIds)) {
            $average = $this->userMetaRepository
                ->getTable()
                ->select('AVG(value) AS avg_product_payment')
                ->where(['key' => 'product_payments', 'user_id' => $usersIds])
                ->fetch();

            $this->template->avgProductPayments = $average->avg_product_payment;

            $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
            $this->template->render();
        }
    }
}
