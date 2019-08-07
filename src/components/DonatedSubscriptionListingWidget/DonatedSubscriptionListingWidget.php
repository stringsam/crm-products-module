<?php

namespace Crm\ProductsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Nette\Database\IRow;

class DonatedSubscriptionListingWidget extends BaseWidget
{
    private $templateName = 'donated_subscription_listing_widget.latte';

    public function identifier()
    {
        return 'donatedsubscriptionwidget';
    }

    public function render(IRow $subscription)
    {
        $giftCoupon = $subscription->related('payment_gift_coupons')->fetch();
        if (!$giftCoupon) {
            return;
        }

        $this->template->donor = $giftCoupon->payment->user;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}