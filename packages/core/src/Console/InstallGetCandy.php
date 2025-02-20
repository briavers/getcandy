<?php

namespace GetCandy\Console;

use GetCandy\FieldTypes\TranslatedText;
use GetCandy\Hub\Models\Staff;
use GetCandy\Models\Attribute;
use GetCandy\Models\AttributeGroup;
use GetCandy\Models\Channel;
use GetCandy\Models\Collection;
use GetCandy\Models\CollectionGroup;
use GetCandy\Models\Country;
use GetCandy\Models\Currency;
use GetCandy\Models\CustomerGroup;
use GetCandy\Models\Language;
use GetCandy\Models\ProductType;
use GetCandy\Models\TaxClass;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class InstallGetCandy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'getcandy:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the GetCandy';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        DB::transaction(function () {
            $this->info('Installing GetCandy...');

            $this->info('Publishing configuration...');

            if (!$this->configExists('getcandy')) {
                $this->publishConfiguration();
            } else {
                if ($this->shouldOverwriteConfig()) {
                    $this->info('Overwriting configuration file...');
                    $this->publishConfiguration($force = true);
                } else {
                    $this->info('Existing configuration was not overwritten');
                }
            }

            $this->info('Publishing hub assets');

            if (!Country::count()) {
                $this->info('Importing countries');
                $this->call('getcandy:import:address-data');
            }

            if (!Channel::whereDefault(true)->exists()) {
                $this->info('Setting up default channel');

                Channel::create([
                    'name'    => 'Webstore',
                    'handle'  => 'webstore',
                    'default' => true,
                    'url'     => 'localhost',
                ]);
            }

            if (!Staff::whereAdmin(true)->exists()) {
                $this->info('Create an admin user');

                $firstname = $this->ask('Whats your first name?');
                $lastname = $this->ask('Whats your last name?');
                $email = $this->ask('Whats your email address?');
                $password = $this->secret('Enter a password');

                Staff::create([
                    'firstname' => $firstname,
                    'lastname'  => $lastname,
                    'email'     => $email,
                    'password'  => bcrypt($password),
                    'admin'     => true,
                ]);
            }

            if (!Language::count()) {
                $this->info('Adding default language');

                Language::create([
                    'code'    => 'en',
                    'name'    => 'English',
                    'default' => true,
                ]);
            }

            if (!Currency::whereDefault(true)->exists()) {
                $this->info('Adding a default currency (USD)');

                Currency::create([
                    'code'           => 'USD',
                    'name'           => 'US Dollar',
                    'exchange_rate'  => 1,
                    'format'         => '${value}',
                    'decimal_point'  => '.',
                    'thousand_point' => ',',
                    'decimal_places' => 2,
                    'default'        => true,
                    'enabled'        => true,
                ]);
            }

            if (!CustomerGroup::whereDefault(true)->exists()) {
                $this->info('Adding a default customer group.');

                CustomerGroup::create([
                    'name'    => 'Retail',
                    'handle'  => 'retail',
                    'default' => true,
                ]);
            }

            if (!CollectionGroup::count()) {
                $this->info('Adding an initial collection group');

                CollectionGroup::create([
                    'name'   => 'Main',
                    'handle' => 'main',
                ]);
            }

            if (!TaxClass::count()) {
                $this->info('Adding a default tax class.');

                TaxClass::create([
                    'name' => 'Default Tax Class',
                ]);
            }

            if (!Attribute::count()) {
                $this->info('Setting up initial attributes');

                $group = AttributeGroup::create([
                    'attributable_type' => ProductType::class,
                    'name'              => collect([
                        'en' => 'Details',
                    ]),
                    'handle'   => 'details',
                    'position' => 1,
                ]);

                $collectionGroup = AttributeGroup::create([
                    'attributable_type' => Collection::class,
                    'name'              => collect([
                        'en' => 'Details',
                    ]),
                    'handle'   => 'collection_details',
                    'position' => 1,
                ]);

                Attribute::create([
                    'attribute_type'     => ProductType::class,
                    'attribute_group_id' => $group->id,
                    'position'           => 1,
                    'name'               => [
                        'en' => 'Name',
                    ],
                    'handle'        => 'name',
                    'section'       => 'main',
                    'type'          => TranslatedText::class,
                    'required'      => true,
                    'default_value' => null,
                    'configuration' => [
                        'type' => 'text',
                    ],
                    'system' => true,
                ]);

                Attribute::create([
                    'attribute_type'     => Collection::class,
                    'attribute_group_id' => $collectionGroup->id,
                    'position'           => 1,
                    'name'               => [
                        'en' => 'Name',
                    ],
                    'handle'        => 'name',
                    'section'       => 'main',
                    'type'          => TranslatedText::class,
                    'required'      => true,
                    'default_value' => null,
                    'configuration' => [
                        'type' => 'text',
                    ],
                    'system' => true,
                ]);

                Attribute::create([
                    'attribute_type'     => ProductType::class,
                    'attribute_group_id' => $group->id,
                    'position'           => 2,
                    'name'               => [
                        'en' => 'Description',
                    ],
                    'handle'        => 'description',
                    'section'       => 'main',
                    'type'          => TranslatedText::class,
                    'required'      => true,
                    'default_value' => null,
                    'configuration' => [
                        'type' => 'richtext',
                    ],
                    'system' => true,
                ]);

                Attribute::create([
                    'attribute_type'     => Collection::class,
                    'attribute_group_id' => $collectionGroup->id,
                    'position'           => 2,
                    'name'               => [
                        'en' => 'Description',
                    ],
                    'handle'        => 'description',
                    'section'       => 'main',
                    'type'          => TranslatedText::class,
                    'required'      => true,
                    'default_value' => null,
                    'configuration' => [
                        'type' => 'richtext',
                    ],
                    'system' => true,
                ]);
            }

            if (!ProductType::count()) {
                $this->info('Adding a product type.');

                $type = ProductType::create([
                    'name' => 'Stock',
                ]);

                $type->mappedAttributes()->attach(
                    Attribute::whereAttributeType(ProductType::class)->get()->pluck('id')
                );
            }

            $this->info('GetCandy is now installed.');

            if ($this->confirm('Would you like to show some love by starring the repo?')) {
                $exec = PHP_OS_FAMILY === 'Windows' ? 'start' : 'open';

                exec("{$exec} https://github.com/getcandy/getcandy");

                $this->line("Thanks, you're awesome!");
            }
        });
    }

    /**
     * Checks if config exists given a filename.
     *
     * @param string $fileName
     *
     * @return bool
     */
    private function configExists($fileName): bool
    {
        if (!File::isDirectory(config_path($fileName))) {
            return false;
        }

        return !empty(File::allFiles(config_path($fileName)));
    }

    /**
     * Returns a prompt if config exists and ask to override it.
     *
     * @return bool
     */
    private function shouldOverwriteConfig(): bool
    {
        return $this->confirm(
            'Config file already exists. Do you want to overwrite it?',
            false
        );
    }

    /**
     * Publishes configuration for the Service Provider.
     *
     * @param bool $forcePublish
     *
     * @return void
     */
    private function publishConfiguration($forcePublish = false): void
    {
        $params = [
            '--provider' => "GetCandy\GetCandyServiceProvider",
            '--tag'      => 'getcandy',
        ];

        if ($forcePublish === true) {
            $params['--force'] = true;
        }

        $this->call('vendor:publish', $params);
    }
}
