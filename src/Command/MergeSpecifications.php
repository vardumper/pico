<?php

declare(strict_types=1);

namespace PicoCss\Pico\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

final class MergeSpecifications extends Command
{
   private const HTML_DEFINITION_PATH = __DIR__ . '/../../vendor/vardumper/extended-htmldocument/src/Resources/specifications/html5.yaml';
   private const PICOCSS_DEFINITION_PATH = __DIR__ . '/../Resources/picocss.yaml';

   public function __invoke($element, InputInterface $input, OutputInterface $output): int
   {
      $frameworkSpecs = Yaml::parseFile(self::PICOCSS_DEFINITION_PATH);
      $htmlSpecs = Yaml::parseFile(self::HTML_DEFINITION_PATH);
      $output = $htmlSpecs;

      // Global CSS attributes that can be used on _ANY_ element
      if (array_key_exists('*', $frameworkSpecs)) {
         foreach ($htmlSpecs as $element => $props) {
            if (!isset($output[$element]['attributes'])) {
               $output[$element]['attributes'] = $frameworkSpecs['*']['attributes'];

               continue;
            }
            $output[$element]['attributes'] = \array_merge_recursive($output[$element]['attributes'], $frameworkSpecs['*']['attributes']);
         }
      }

      // Regex matching
      foreach (array_keys($frameworkSpecs) as $pattern) {
         if (@preg_match($pattern, '') !== false) {
            $keys = array_keys($output);
            $result = preg_grep($pattern, $keys);

            foreach ($result as $key) {
               if (!isset($htmlSpecs[$key]['attributes'])) {
                  $output[$key]['attributes'] = $frameworkSpecs[$pattern]['attributes'];

                  continue;
               }
               $output[$key]['attributes'] = \array_merge_recursive($output[$key]['attributes'], $frameworkSpecs[$pattern]['attributes']);
            }

            unset($frameworkSpecs[$pattern]); // Remove regex key from framework specs after processing (so we can deep merge all non-regex keys later)
         }
      }

      // Deep merge everything else
      $output = \array_merge_recursive($output, $frameworkSpecs);
      if (isset($output['*'])) {
         unset($output['*']); // Remove global wildcard key from output
      }
      \file_put_contents(__DIR__ . '/../Resources/picocss-html5-specification.yaml', Yaml::dump($output, 10, 2));
      return Command::SUCCESS;
   }
}
