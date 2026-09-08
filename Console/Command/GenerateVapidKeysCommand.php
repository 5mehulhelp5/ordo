<?php
declare(strict_types=1);

namespace Ordo\Automation\Console\Command;

use Ordo\Automation\Model\Push\Base64Url;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento ordo:push:vapid:generate` - a fresh P-256 keypair for the "Push Notifications
 * (Web Push)" config section. Admin has no other reasonable way to produce one: browsers require
 * an exact base64url-encoded raw EC point/scalar, not a PEM file, so pointing someone at plain
 * `openssl ecparam` output would leave them to convert the format by hand.
 */
class GenerateVapidKeysCommand extends Command
{
    public function __construct(
        private readonly Base64Url $base64Url,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('ordo:push:vapid:generate');
        $this->setDescription('Generate a VAPID key pair for the "Push Notifications (Web Push)" config section.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($key === false) {
            $output->writeln(
                '<error>Failed to generate an EC key pair - is the OpenSSL PHP extension enabled?</error>'
            );
            return Command::FAILURE;
        }

        $details = openssl_pkey_get_details($key);
        $x = $details['ec']['x'] ?? null;
        $y = $details['ec']['y'] ?? null;
        $d = $details['ec']['d'] ?? null;
        if (!is_string($x) || !is_string($y) || !is_string($d)) {
            $output->writeln('<error>Failed to read the generated key pair.</error>');
            return Command::FAILURE;
        }

        $publicPoint = "\x04" . $this->pad32($x) . $this->pad32($y);
        $publicKey = $this->base64Url->encode($publicPoint);
        $privateKey = $this->base64Url->encode($this->pad32($d));

        $output->writeln('VAPID Public Key:');
        $output->writeln($publicKey);
        $output->writeln('');
        $output->writeln('VAPID Private Key:');
        $output->writeln($privateKey);
        $output->writeln('');
        $output->writeln(
            'Paste these into Stores > Configuration > Ordo Automation > Push Notifications (Web Push).'
        );

        return Command::SUCCESS;
    }

    private function pad32(string $bytes): string
    {
        return str_pad($bytes, 32, "\x00", STR_PAD_LEFT);
    }
}
