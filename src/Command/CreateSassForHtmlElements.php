<?php

declare(strict_types=1);

namespace PicoCss\Pico\Command;

use Html\Delegator\HTMLDocumentDelegator;
use Symfony\Component\Console\Command\Command;

class CreateSassForHtmlElements extends Command
{
   private const SOURCE_NAMESPACES = [
      // 'Block',
      // 'Inline',
      'Void',
   ];

   public function __invoke($input, $output): int
   {
      foreach (self::SOURCE_NAMESPACES as $namespace) {
         // vendor/vardumper/extended-htmldocument/src/Element/Block
         $files = glob('vendor/vardumper/extended-htmldocument/src/Element/' . $namespace . '/*.php');
         $classes = [];

         $namespacePath = 'scss/' . strtolower($namespace);
         if (!is_dir($namespacePath)) {
            \mkdir($namespacePath, 0777, true);
         }


         $dom = HTMLDocumentDelegator::createEmpty();
         $this->deleteIndexFile($namespace);

         foreach ($files as $file) {
            $className = basename($file, '.php');
            $classFQN = "\\Html\\Element\\$namespace\\$className";
            $instance = $classFQN::create($dom);
            echo "Creating SASS for {$classFQN}\n";

            $kebabName = $this->camelToKebab($className);
            // @todo add references to all occurrences in original pico code to new scss file
            $content = $this->getTemplateContent($instance::QUALIFIED_NAME);
            \file_put_contents($namespacePath . \DIRECTORY_SEPARATOR . '_' . $kebabName . '.scss', $content);
            // @todo add import to new scss file into _index.scss

            $this->addToIndexFile($namespace, $kebabName);
         }
      }

      return Command::SUCCESS;
   }

   private function deleteIndexFile(string $namespace): void
   {
      $indexFilePath = 'scss/' . strtolower($namespace) . '/_' . strtolower($namespace) . '.scss';
      unlink($indexFilePath);
   }

   private function addToIndexFile(string $namespace, string $kebabName): void
   {
      $indexFilePath = 'scss/' . strtolower($namespace) . '/_' . strtolower($namespace) . '.scss';
      $importLine = "@use '$kebabName';\n";
      \file_put_contents($indexFilePath, $importLine, FILE_APPEND);
   }

   private function camelToKebab(string $input): string
   {
      return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $input));
   }

   private function getTemplateContent(string $elementName): string
   {
      $occurrences = $this->findOccurrencesInPicoCode($elementName);
      $occurrencesList = \implode("\n", array_map(fn($occurrence) => " * Found in {$occurrence['file']}#{$occurrence['line']}: `{$occurrence['content']}`", $occurrences));
      return <<<SASS
/** SASS for the <$elementName> HTML element
 *
$occurrencesList
 *
 * Add your styles here
 */
SASS;
   }

   /** looks into all pre-existing pico scss files for occurrences of the given element name */
   private function findOccurrencesInPicoCode(string $elementName): array
   {
      if (strlen($elementName) === 1) {
         return [];
      }

      // recursive glob on all scss files, excpet inline and block folders
      $files = glob('scss/**/*.scss', GLOB_BRACE);
      $occurrences = [];

      foreach ($files as $file) {
         if (\strstr($file, 'scss/inline') || \strstr($file, 'scss/block')) {
            continue;
         }
         // add filename and line number of each occurrence to the occurrences array
         $lines = file($file);
         foreach ($lines as $lineNumber => $lineContent) {
            if (str_contains($lineContent, $elementName)) {
               $occurrences[] = [
                  'file' => $file,
                  'line' => $lineNumber + 1,
                  'content' => trim($lineContent),
               ];
            }
         }
      }

      return $occurrences;
   }
}
