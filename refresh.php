<?php

/**
 * Refresh script for Silverstripe CMS 6 polyfill using hybrid AST approach
 * 
 * This script:
 * 1. Reads the cms6-equivalence.yml mapping file
 * 2. Copies each CMS 5 class to its new CMS 6 namespace location
 * 3. Uses PHP Parser AST for precise namespace and deprecation transformations
 * 4. Uses Rector for final cleanup
 * 5. Adds polyfill headers to the generated files
 */

require_once 'vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Process\Process;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;

class PolyfillRefresher
{
    private string $frameworkPath;
    private array $equivalenceMap;
    private array $copiedFiles = [];
    private array $classMapping;
    private array $namespaceMapping;

    public function __construct()
    {
        $this->frameworkPath = 'silverstripe-temp/vendor/silverstripe/framework';
        $this->loadEquivalenceMap();
        $this->setupMappings();
        $this->setupTempFramework();
    }

    private function loadEquivalenceMap(): void
    {
        if (!file_exists('cms6-equivalence.yml')) {
            throw new Exception('cms6-equivalence.yml file not found');
        }
        
        $this->equivalenceMap = Yaml::parseFile('cms6-equivalence.yml');
        echo "Loaded " . count($this->equivalenceMap) . " class mappings\n";
    }

    private function setupMappings(): void
    {
        // Class name mappings for specific classes that change names
        $this->classMapping = [
            'RequiredFields' => 'RequiredFieldsValidator',
            'ViewableData' => 'ModelData',
            'ViewableData_Customised' => 'ModelDataCustomised',
            'ViewableData_Debugger' => 'ModelDataDebugger',
            'SSViewer_BasicIteratorSupport' => 'BasicIteratorSupport',
            'PasswordValidator' => 'RulesPasswordValidator',
        ];
        
        // Build namespace mapping from the equivalence map
        $this->namespaceMapping = [];
        foreach ($this->equivalenceMap as $cms5Path => $cms6Path) {
            $oldNamespace = $this->pathToNamespace($cms5Path);
            $newNamespace = $this->pathToNamespace($cms6Path);
            if ($oldNamespace !== $newNamespace) {
                $this->namespaceMapping[$oldNamespace] = $newNamespace;
            }
        }
    }

    private function setupTempFramework(): void
    {
        if (!is_dir($this->frameworkPath)) {
            echo "📦 Setting up temporary Silverstripe framework...\n";
            $process = new Process(['composer', 'create-project', 'silverstripe/installer', 'silverstripe-temp', '--prefer-dist', '--no-install', '--stability=stable']);
            $process->run();
            
            if (!$process->isSuccessful()) {
                throw new Exception('Failed to create temporary Silverstripe project: ' . $process->getErrorOutput());
            }
            
            $installProcess = new Process(['composer', 'install', '--no-scripts'], 'silverstripe-temp');
            $installProcess->run();
            
            if (!$installProcess->isSuccessful()) {
                throw new Exception('Failed to install Silverstripe dependencies: ' . $installProcess->getErrorOutput());
            }
        }
    }

    public function refresh(): void
    {
        echo "🚀 Starting polyfill refresh with AST transformations...\n";
        
        // Clean existing src directory
        if (is_dir('src')) {
            $this->removeDirectory('src');
        }
        mkdir('src', 0755, true);
        
        // Copy and transform source files
        foreach ($this->equivalenceMap as $cms5Path => $cms6Path) {
            $this->copyAndTransformFile($cms5Path, $cms6Path);
        }
        
        // Run Rector for final cleanup
        $this->runRector();
        
        // Clean up temporary framework
        $this->removeDirectorySafely('silverstripe-temp');
        
        echo "✅ Polyfill refresh completed!\n";
    }

    private function copyAndTransformFile(string $cms5Path, string $cms6Path): void
    {
        $sourcePath = $this->frameworkPath . '/' . $cms5Path;
        $targetPath = $cms6Path;
        
        if (!file_exists($sourcePath)) {
            echo "⚠️  Source file not found: {$sourcePath}\n";
            return;
        }
        
        echo "📄 Processing: {$cms5Path} -> {$cms6Path}\n";
        
        // Create target directory if it doesn't exist
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        // Read and transform the source file
        $sourceContent = file_get_contents($sourcePath);
        $transformedContent = $this->transformPhpFile($sourceContent, $cms5Path, $cms6Path);
        
        // Write transformed content
        file_put_contents($targetPath, $transformedContent);
        $this->copiedFiles[] = $targetPath;
    }

    private function transformPhpFile(string $content, string $cms5Path, string $cms6Path): string
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $prettyPrinter = new Standard();
        
        try {
            $ast = $parser->parse($content);
            
            // Create node visitor for transformations
            $visitor = new PolyfillTransformVisitor($cms5Path, $cms6Path, $this->classMapping, $this->namespaceMapping);
            
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            
            // Transform the AST
            $ast = $traverser->traverse($ast);
            
            // Generate transformed code
            $transformedCode = $prettyPrinter->prettyPrintFile($ast);
            
            // Add polyfill header
            return $this->addPolyfillHeader($transformedCode, $cms5Path, $cms6Path);
            
        } catch (Exception $e) {
            echo "⚠️  Failed to parse {$cms5Path}: " . $e->getMessage() . "\n";
            return $content; // Return original content if parsing fails
        }
    }

    private function addPolyfillHeader(string $content, string $cms5Path, string $cms6Path): string
    {
        $originalClass = $this->pathToNamespace($cms5Path) . '\\' . $this->extractClassName($cms5Path);
        $polyfillClass = $this->pathToNamespace($cms6Path) . '\\' . $this->extractClassName($cms6Path);
        
        $header = "<?php\n\n/**\n * CMS 6 Polyfill for {$originalClass}\n * \n * This class provides forward compatibility by making the CMS 6 namespace\n * available in CMS 5, allowing you to migrate your code early.\n * \n * @package silverstripe-six-polyfill\n */\n\n";
        
        // Remove existing <?php tag and add our header
        $content = preg_replace('/^<\?php\s*\n?/s', '', $content);
        return $header . $content;
    }

    private function runRector(): void
    {
        echo "🔄 Running Rector for final cleanup...\n";
        
        $process = new Process(['vendor/bin/rector', 'process', '--config=rector.php', '--no-diffs']);
        $process->run();
        
        if ($process->isSuccessful()) {
            echo "✅ Rector cleanup completed\n";
        } else {
            echo "⚠️  Rector had some issues but continuing...\n";
        }
    }

    private function pathToNamespace(string $path): string
    {
        $pathParts = explode('/', dirname($path));
        array_shift($pathParts); // Remove 'src'
        return 'SilverStripe\\' . implode('\\', $pathParts);
    }

    private function extractClassName(string $path): string
    {
        return basename($path, '.php');
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function removeDirectorySafely(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        // Use system rm command for better handling of symlinks
        $process = new Process(['rm', '-rf', $dir]);
        $process->run();
        
        if (!$process->isSuccessful()) {
            echo "⚠️  Could not fully remove temporary directory: {$dir}\n";
        }
    }
}

/**
 * AST Visitor for transforming SilverStripe classes
 */
class PolyfillTransformVisitor extends NodeVisitorAbstract
{
    private string $cms5Path;
    private string $cms6Path;
    private array $classMapping;
    private array $namespaceMapping;

    public function __construct(string $cms5Path, string $cms6Path, array $classMapping, array $namespaceMapping)
    {
        $this->cms5Path = $cms5Path;
        $this->cms6Path = $cms6Path;
        $this->classMapping = $classMapping;
        $this->namespaceMapping = $namespaceMapping;
    }

    public function enterNode(Node $node)
    {
        // Remove deprecation warnings about class renaming - check this first
        if ($this->isDeprecationNoticeAboutRenaming($node)) {
            return NodeTraverser::REMOVE_NODE;
        }
        
        return null;
    }
    
    public function leaveNode(Node $node)
    {
        // Transform namespace declarations
        if ($node instanceof Node\Stmt\Namespace_) {
            if ($node->name) {
                $currentNamespace = $node->name->toString();
                foreach ($this->namespaceMapping as $old => $new) {
                    if ($currentNamespace === $old) {
                        $node->name = new Node\Name($new);
                        break;
                    }
                }
            }
        }
        
        // Transform class names
        if ($node instanceof Node\Stmt\Class_) {
            if ($node->name) {
                $currentClassName = $node->name->toString();
                if (isset($this->classMapping[$currentClassName])) {
                    $node->name = new Node\Identifier($this->classMapping[$currentClassName]);
                }
            }
        }
        
        return null;
    }

    private function isDeprecationNoticeAboutRenaming(Node $node): bool
    {
        // Remove expression statements that contain deprecation notices about renaming
        if ($node instanceof Node\Stmt\Expression) {
            $expr = $node->expr;
            
            // Handle Deprecation::withSuppressedNotice calls
            if ($expr instanceof Node\Expr\StaticCall &&
                $this->isDeprecationCall($expr, 'withSuppressedNotice')) {
                
                // Check if the closure contains a renaming notice
                if (isset($expr->args[0]) && $expr->args[0]->value instanceof Node\Expr\Closure) {
                    $closure = $expr->args[0]->value;
                    foreach ($closure->stmts as $stmt) {
                        if ($this->isRenamingDeprecationStatement($stmt)) {
                            return true;
                        }
                    }
                }
            }
            
            // Handle direct Deprecation::notice calls  
            if ($expr instanceof Node\Expr\StaticCall &&
                $this->isDeprecationCall($expr, 'notice')) {
                return $this->hasRenamingMessage($expr);
            }
            
            // Handle other deprecation method calls like noticeWithNoReplacement
            if ($expr instanceof Node\Expr\StaticCall &&
                $this->isDeprecationMethodCall($expr)) {
                return $this->hasRenamingMessage($expr);
            }
        }
        
        return false;
    }

    private function isDeprecationCall(Node\Expr\StaticCall $call, string $method): bool
    {
        return $call->class instanceof Node\Name &&
               $call->class->toString() === 'Deprecation' &&
               $call->name instanceof Node\Identifier &&
               $call->name->toString() === $method;
    }
    
    private function isDeprecationMethodCall(Node\Expr\StaticCall $call): bool
    {
        if (!($call->class instanceof Node\Name) || $call->class->toString() !== 'Deprecation') {
            return false;
        }
        
        if (!($call->name instanceof Node\Identifier)) {
            return false;
        }
        
        $method = $call->name->toString();
        $deprecationMethods = ['notice', 'withSuppressedNotice', 'noticeWithNoReplacement', 'noticeWithNoReplacment'];
        return in_array($method, $deprecationMethods);
    }

    private function isRenamingDeprecationStatement(Node\Stmt $stmt): bool
    {
        if ($stmt instanceof Node\Stmt\Expression &&
            $stmt->expr instanceof Node\Expr\StaticCall &&
            $this->isDeprecationCall($stmt->expr, 'notice')) {
            return $this->hasRenamingMessage($stmt->expr);
        }
        return false;
    }

    private function hasRenamingMessage(Node\Expr\StaticCall $call): bool
    {
        // Check different argument positions for the message
        foreach ($call->args as $arg) {
            if ($arg->value instanceof Node\Scalar\String_) {
                $message = $arg->value->value;
                if (str_contains($message, 'Will be renamed') || 
                    str_contains($message, 'renamed to') ||
                    str_contains($message, 'Will be moved to') ||
                    str_contains($message, 'moved to')) {
                    return true;
                }
            }
        }
        return false;
    }
}

// Run the refresh if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $refresher = new PolyfillRefresher();
        $refresher->refresh();
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}