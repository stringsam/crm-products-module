<?php

namespace Crm\ProductsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Nette\Database\Connection;

class AvgProductsPaymentWidget extends BaseWidget
{
    private $templateName = 'avg_products_payment_widget.latte';

    private $database;

    public function __construct(WidgetManager $widgetManager, Connection $database)
    {
        parent::__construct($widgetManager);
        $this->database = $database;
    }

    public function identifier()
    {
        return 'avgproductspaymentwidget';
    }

    public function render(array $usersIds)
    {
        if (count($usersIds)) {
            $usersIds = implode(',', $usersIds);
            $query = "SELECT AVG(value) AS avg_product_payment FROM user_meta WHERE `key`='product_payments' AND user_id IN (".$usersIds.")";
            $average = $this->database->query($query)->fetch();
            $this->template->avgProductPayments = $average->avg_product_payment;
        }

        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
