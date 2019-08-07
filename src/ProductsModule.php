<?php

namespace Crm\ProductsModule;

use Crm\ApplicationModule\Commands\CommandsContainerInterface;
use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Config\ConfigsCache;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Event\EventsStorage;
use Crm\ApplicationModule\LayoutManager;
use Crm\ApplicationModule\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Menu\MenuItem;
use Crm\ApplicationModule\SeederManager;
use Crm\ApplicationModule\User\UserDataRegistrator;
use Crm\ApplicationModule\Widget\WidgetManagerInterface;
use Crm\ProductsModule\DataProvider\PaymentFormDataProvider;
use Crm\ProductsModule\DataProvider\PaymentsAdminFilterFormDataProvider;
use Crm\ProductsModule\Repository\ProductsRepository;
use Crm\ProductsModule\Seeders\AddressTypesSeeder;
use Crm\ProductsModule\Seeders\ConfigsSeeder;
use Kdyby\Translation\Translator;
use League\Event\Emitter;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;
use Nette\DI\Container;
use Symfony\Component\Console\Output\OutputInterface;

class ProductsModule extends CrmModule
{
    private $applicationConfig;

    private $configsCache;

    private $productsCache;

    private $productsRepository;

    public function __construct(
        Container $container,
        Translator $translator,
        ApplicationConfig $applicationConfig,
        ConfigsCache $configsCache,
        ProductsCache $productsCache,
        ProductsRepository $productsRepository
    ) {
        parent::__construct($container, $translator);
        $this->applicationConfig = $applicationConfig;
        $this->configsCache = $configsCache;
        $this->productsCache = $productsCache;
        $this->productsRepository = $productsRepository;
    }

    public function registerAdminMenuItems(MenuContainerInterface $menuContainer)
    {
        $mainMenu = new MenuItem(
            $this->translator->translate('products.menu.shop'),
            '#',
            'fa fa-shekel-sign',
            550
        );
        $menuItem1 = new MenuItem(
            $this->translator->translate('products.menu.products'),
            ':Products:ProductsAdmin:default',
            'fa fa-cube',
            500
        );
        $menuItem2 = new MenuItem(
            $this->translator->translate('products.menu.tags'),
            ':Products:TagsAdmin:default',
            'fa fa-tag',
            600
        );
        $menuItem3 = new MenuItem(
            $this->translator->translate('products.menu.orders'),
            ':Products:OrdersAdmin:default',
            'fa fa-paper-plane',
            700
        );
        $menuItemZlavomat1 = new MenuItem(
            $this->translator->translate('zlavomat.menu.default'),
            ':Zlavomat:ZlavomatAdmin:default',
            'fa fa-list',
            800
        );
        $menuItemZlavomat2 = new MenuItem(
            $this->translator->translate('zlavomat.menu.used_coupons'),
            ':Zlavomat:ZlavomatAdmin:used-coupons',
            'fa fa-list',
            900
        );

        $mainMenu->addChild($menuItem1);
        $mainMenu->addChild($menuItem2);
        $mainMenu->addChild($menuItem3);
        $mainMenu->addChild($menuItemZlavomat1);
        $mainMenu->addChild($menuItemZlavomat2);

        $menuContainer->attachMenuItem($mainMenu);

        // dashboard menu item

        $menuItem = new MenuItem(
            $this->translator->translate('products.menu.stats'),
            ':Products:Dashboard:default',
            'fa fa-shekel-sign',
            350
        );
        $menuContainer->attachMenuItemToForeignModule('#dashboard', $mainMenu, $menuItem);
    }

    public function registerCommands(CommandsContainerInterface $commandsContainer)
    {
        $commandsContainer->registerCommand($this->getInstance(\Crm\ProductsModule\Commands\ActivatePurchasedGiftCouponsCommand::class));
        $commandsContainer->registerCommand($this->getInstance(\Crm\ProductsModule\Commands\CalculateAveragesCommand::class));
    }

    public function registerFrontendMenuItems(MenuContainerInterface $menuContainer)
    {
        $menuItem = new MenuItem($this->translator->translate('products.menu.orders'), ':Products:Orders:My', '', 150);
        $menuContainer->attachMenuItem($menuItem);

        $menuItem = new MenuItem($this->translator->translate('products.menu.books'), ':Products:Orders:Library', '', 155);
        $menuContainer->attachMenuItem($menuItem);
    }

    public function registerEventHandlers(Emitter $emitter)
    {
        $emitter->addListener(
            \Crm\PaymentsModule\Events\PaymentChangeStatusEvent::class,
            $this->getInstance(\Crm\ProductsModule\Events\PaymentStatusChangeHandler::class)
        );
    }

    public function registerUserData(UserDataRegistrator $dataRegistrator)
    {
        $dataRegistrator->addUserDataProvider($this->getInstance(\Crm\ProductsModule\User\OrdersUserDataProvider::class));
    }

    public function registerRoutes(RouteList $router)
    {
        $shopHost = $this->configsCache->get('shop_host');
        if (!$shopHost) {
            $shopHost = $this->applicationConfig->get('shop_host');
            $this->configsCache->add('shop_host', $shopHost);
        }

        if ($shopHost) {
            foreach ($this->productsCache->all() as $product) {
                $router[] = new Route("//" . $shopHost . "/show/<id {$product->id}>/{$product->code}", [
                    'module' => 'Products',
                    'presenter' => 'Shop',
                    'action' => 'show',
                    'code' => $product->code,
                ]);

                $router[] = new Route("//" . $shopHost . "/product/{$product->code}", [
                    'module' => 'Products',
                    'presenter' => 'Shop',
                    'action' => 'product',
                    'code' => $product->code,
                ]);
            }

            $request = $this->getInstance(\Nette\Http\Request::class);
            if ($request->url->host === $shopHost) {
                $router[] = new Route('//' . $shopHost . '/sales-funnel/sales-funnel/<action>[/<id>]', 'SalesFunnel:SalesFunnel:default');
            }

            $router[] = new Route('//' . $shopHost . '/<action>[/<id>[/<code>]]', 'Products:Shop:default');
        }
    }

    public function cache(OutputInterface $output, array $tags = [])
    {
        if (empty($tags)) {
            $productCount = $this->productsRepository->getTable()->count('*');
            if ($productCount) {
                $this->productsCache->removeAll();
                foreach ($this->productsRepository->getTable() as $product) {
                    $this->productsCache->add($product->id, $product->code);
                    $output->writeln("  * adding product <info>{$product->code}</info>");
                }
            }
        }
    }

    public function registerLayouts(LayoutManager $layoutManager)
    {
        $layoutManager->registerLayout(
            'shop',
            realpath(__DIR__ . '/templates/@shop_layout.latte')
        );
    }

    public function registerSeeders(SeederManager $seederManager)
    {
        $seederManager->addSeeder($this->getInstance(ConfigsSeeder::class));
        $seederManager->addSeeder($this->getInstance(AddressTypesSeeder::class));
    }

    public function registerDataProviders(DataProviderManager $dataProviderManager)
    {
        $dataProviderManager->registerDataProvider(
            'payments.dataprovider.list_filter_form',
            $this->getInstance(PaymentsAdminFilterFormDataProvider::class)
        );
        $dataProviderManager->registerDataProvider(
            'payments.dataprovider.payment_form',
            $this->getInstance(PaymentFormDataProvider::class)
        );
    }

    public function registerEvents(EventsStorage $eventsStorage)
    {
        $eventsStorage->register('order_status_change', Events\OrderStatusChangeEvent::class);
        $eventsStorage->register('product_save', Events\ProductSaveEvent::class);
    }

    public function registerWidgets(WidgetManagerInterface $widgetManager)
    {
        $widgetManager->registerWidget(
            'payments.admin.payment_item_listing',
            $this->getInstance(\Crm\ProductsModule\Components\ProductItemsListWidget::class)
        );
        $widgetManager->registerWidgetFactory(
            'admin.payments.listing.action',
            $this->getInstance(\Crm\ProductsModule\Components\GiftCouponsFactoryInterface::class),
            400
        );
        $widgetManager->registerWidget(
            'subscriptions.admin.user_subscriptions_listing.subscription',
            $this->getInstance(\Crm\ProductsModule\Components\DonatedSubscriptionListingWidget::class)
        );
        $widgetManager->registerWidget(
            'payments.admin.total_user_payments',
            $this->getInstance(\Crm\ProductsModule\Components\TotalShopPaymentsWidget::class)
        );
    }
}