<?php

/**
 * Project: WPPluginModernizer
 * File: CreateChildPluginCommand.php
 * Author: Kaiser Keenmon
 * Date: 3/26/24
 */

namespace WPPluginModernizer\Modernize\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use WPPluginModernizer\Modernize\Traits\Commands\PluginDirectory;
use WPPluginModernizer\Modernize\Utilities\Strings;

class CreateChildPluginCommand extends Command
{
    use PluginDirectory;

    public function __construct()
    {
        parent::__construct();
        $this->initializePluginDirectory();
    }

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('make:child-plugin')
            ->setDescription('Creates a new plugin from the template.')
            ->setHelp('This command allows you to create a new WordPress plugin from a template')
            ->addArgument('pluginName', InputArgument::REQUIRED, 'The name of the new plugin');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pluginName = $input->getArgument('pluginName');
        $targetDir = dirname($this->pluginDirPath) . '/' . Strings::sanitizeTitleWithDashes($pluginName);

        $filesystem = new Filesystem();

        try {
            // Copy the ChildPlugin skeleton to the new location
            $sourceDir = $this->pluginDirPath . '/src/Modernize/templates/ChildPlugin/';
            $filesystem->mirror($sourceDir, $targetDir);

            // Set modernize to be executable
            $modernizeScriptPath = $targetDir . '/modernize'; // Assuming the script's name is 'modernize'
            if (file_exists($modernizeScriptPath)) {
                chmod($modernizeScriptPath, 0755); // Make the script executable
                $output->writeln('<info>modernize script set to executable.</info>');
            }

            // Rename the main plugin file
            $originalPluginFileName = 'child-plugin.php';
            $newPluginFileName = Strings::sanitizeTitleWithDashes($pluginName) . '.php';
            $filesystem->rename($targetDir . '/' . $originalPluginFileName, $targetDir . '/' . $newPluginFileName);

            // Update the plugin header
            $fileContents = file_get_contents($targetDir . '/' . $newPluginFileName);
            $replacedContents = str_replace('Template Plugin Name', $pluginName, $fileContents);
            file_put_contents($targetDir . '/' . $newPluginFileName, $replacedContents);

            $output->writeln('<info>Plugin created successfully.</info>');
        } catch (IOExceptionInterface $exception) {
            $output->writeln('<error>An error occurred while creating the plugin.</error>');
        }

        return Command::SUCCESS;
    }
}

