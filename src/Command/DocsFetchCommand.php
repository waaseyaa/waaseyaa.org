<?php

declare(strict_types=1);

namespace WaaseyaaOrg\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WaaseyaaOrg\Service\DocsFetcher;
use WaaseyaaOrg\Service\DocsNavigationBuilder;

#[AsCommand(name: 'docs:fetch', description: 'Fetch package READMEs from GitHub and build docs navigation index')]
final class DocsFetchCommand extends Command
{
    public function __construct(
        private readonly DocsFetcher $fetcher,
        private readonly DocsNavigationBuilder $navBuilder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Fetching package READMEs from GitHub...</info>');

        $result = $this->fetcher->fetch();

        $output->writeln(sprintf('  Fetched: %d packages', count($result['fetched'])));

        if (!empty($result['skipped'])) {
            $output->writeln(sprintf('  Skipped (empty): %s', implode(', ', $result['skipped'])));
        }

        if (!empty($result['errors'])) {
            $output->writeln(sprintf('<error>  Errors: %s</error>', implode(', ', $result['errors'])));
        }

        $output->writeln('<info>Building navigation index...</info>');

        try {
            $indexPath = $this->navBuilder->buildAndSave();
            $output->writeln(sprintf('  Index written to: %s', $indexPath));
        } catch (\RuntimeException $e) {
            $output->writeln(sprintf('<error>Build failed: %s</error>', $e->getMessage()));
            return self::FAILURE;
        }

        if (!empty($result['errors'])) {
            $output->writeln('<error>Failed: some packages could not be fetched. Deploy should not continue with missing docs.</error>');
            return self::FAILURE;
        }

        $output->writeln('<info>Done.</info>');

        return self::SUCCESS;
    }
}
