<?php

/**
 * Refresh script for Silverstripe CMS 6 polyfill using configuration-driven approach
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
    private array $classConfigs = [];
    private array $copiedFiles = [];

    public function __construct()
    {
        $this->frameworkPath = 'silverstripe-temp/vendor/silverstripe/framework';
        $this->loadClassConfigs();
        $this->setupTempFramework();
    }

    private function loadClassConfigs(): void
    {
        if (!file_exists('cms6-equivalence.yml')) {
            throw new Exception('cms6-equivalence.yml file not found');
        }
        
        $config = Yaml::parseFile('cms6-equivalence.yml');
        
        if (!isset($config['classes'])) {
            throw new Exception('No classes configuration found in cms6-equivalence.yml');
        }
        
        $this->classConfigs = $config['classes'];
        echo "Loaded " . count($this->classConfigs) . " class configurations\n";
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
        echo "🚀 Starting polyfill refresh with configuration-driven transformations...\n";
        
        // Clean existing src directory
        if (is_dir('src')) {
            $this->removeDirectory('src');
        }
        mkdir('src', 0755, true);
        
        // Copy and transform source files
        foreach ($this->classConfigs as $sourceClass => $config) {
            $this->copyAndTransformFile($sourceClass, $config);
        }
        
        // Run Rector for final cleanup
        $this->runRector();
        
        // Add polyfill headers to all generated files
        $this->addPolyfillHeaders();
        
        // Clean up temporary framework
        $this->removeDirectorySafely('silverstripe-temp');
        
        echo "✅ Polyfill refresh completed!\n";
    }

    private function copyAndTransformFile(string $sourceClass, array $config): void
    {
        $sourcePath = $this->frameworkPath . '/' . $config['source_path'];
        $targetPath = $config['target_path'];
        
        if (!file_exists($sourcePath)) {
            echo "⚠️  Source file not found: {$sourcePath}\n";
            return;
        }
        
        echo "📄 Processing: {$config['source_path']} -> {$config['target_path']}\n";
        
        // Create target directory if it doesn't exist
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        // Read and transform the source file
        $sourceContent = file_get_contents($sourcePath);
        $transformedContent = $this->transformPhpFile($sourceContent, $sourceClass, $config);
        
        // Write transformed content
        file_put_contents($targetPath, $transformedContent);
        $this->copiedFiles[] = $targetPath;
    }

    private function transformPhpFile(string $content, string $sourceClass, array $config): string
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $prettyPrinter = new Standard();
        
        try {
            $ast = $parser->parse($content);
            
            // Create node visitor for transformations
            $visitor = new ConfigurablePolyfillTransformVisitor($sourceClass, $config);
            
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            
            // Transform the AST
            $ast = $traverser->traverse($ast);
            
            // Generate transformed code
            $transformedCode = $prettyPrinter->prettyPrintFile($ast);
            
            return $transformedCode;
            
        } catch (Exception $e) {
            echo "⚠️  Failed to parse {$sourceClass}: " . $e->getMessage() . "\n";
            return $content; // Return original content if parsing fails
        }
    }

    private function addPolyfillHeaders(): void
    {
        echo "📝 Adding polyfill headers to generated files...\n";
        
        foreach ($this->classConfigs as $sourceClass => $config) {
            $targetPath = $config['target_path'];
            
            if (file_exists($targetPath)) {
                $this->addPolyfillHeaderToFile($targetPath, $sourceClass, $config);
            }
        }
    }

    private function addPolyfillHeaderToFile(string $filePath, string $sourceClass, array $config): void
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return;
        }

        $targetClass = $config['target_namespace'] . '\\' . $config['target_class'];
        
        $header = "<?php\n\n/**\n * CMS 6 Polyfill for {$sourceClass}\n * \n * This class provides forward compatibility by making the CMS 6 namespace\n * available in CMS 5, allowing you to migrate your code early.\n * \n * @package silverstripe-six-polyfill\n */\n\n";
        
        // Remove existing <?php tag and add our header
        $content = preg_replace('/^<\?php\s*\n?/s', '', $content);
        $newContent = $header . $content;
        
        file_put_contents($filePath, $newContent);
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
 * Configuration-driven AST Visitor for transforming SilverStripe classes
 */
class ConfigurablePolyfillTransformVisitor extends NodeVisitorAbstract
{
    private string $sourceClass;
    private array $config;
    private string $sourceNamespace;
    private string $sourceClassName;
    private array $nodesToRemove = [];

    public function __construct(string $sourceClass, array $config)
    {
        $this->sourceClass = $sourceClass;
        $this->config = $config;
        
        // Parse source class name and namespace
        $parts = explode('\\', $sourceClass);
        $this->sourceClassName = array_pop($parts);
        $this->sourceNamespace = implode('\\', $parts);
    }

    public function enterNode(Node $node)
    {
        // Remove deprecation notices in constructors
        if ($node instanceof Node\Stmt\Expression) {
            $expr = $node->expr;
            
            // Mark Deprecation::withSuppressedNotice calls about renaming for removal
            if ($expr instanceof Node\Expr\StaticCall &&
                $this->isDeprecationCall($expr, 'withSuppressedNotice') &&
                $this->isAboutClassRenaming($expr)) {
                $this->nodesToRemove[] = $node;
            }
            
            // Mark direct Deprecation::notice calls about renaming for removal
            if ($expr instanceof Node\Expr\StaticCall &&
                $this->isDeprecationCall($expr, 'notice') &&
                $this->isAboutClassRenaming($expr)) {
                $this->nodesToRemove[] = $node;
            }
            
            // Mark noticeWithNoReplacment calls about renaming for removal
            if ($expr instanceof Node\Expr\StaticCall &&
                $this->isDeprecationCall($expr, 'noticeWithNoReplacment') &&
                $this->isAboutClassRenaming($expr)) {
                $this->nodesToRemove[] = $node;
            }
        }
        
        return null;
    }
    
    public function leaveNode(Node $node)
    {
        // Remove marked deprecation nodes
        if (in_array($node, $this->nodesToRemove, true)) {
            return NodeTraverser::REMOVE_NODE;
        }
        
        // Transform namespace declarations
        if ($node instanceof Node\Stmt\Namespace_) {
            if ($node->name && $node->name->toString() === $this->sourceNamespace) {
                $targetNamespace = $this->config['target_namespace'];
                $node->name = new Node\Name($targetNamespace);
                return $node;
            }
        }
        
        // Transform class names
        if ($node instanceof Node\Stmt\Class_) {
            if ($node->name && $node->name->toString() === $this->sourceClassName) {
                $targetClassName = $this->config['target_class'];
                if ($targetClassName !== $this->sourceClassName) {
                    $node->name = new Node\Identifier($targetClassName);
                    return $node;
                }
            }
        }
        
        return null;
    }
    
    private function isAboutClassRenaming(Node\Expr\StaticCall $call): bool
    {
        // Check for withSuppressedNotice with closure containing renaming notice
        if ($this->isDeprecationCall($call, 'withSuppressedNotice')) {
            if (isset($call->args[0]) && $call->args[0]->value instanceof Node\Expr\Closure) {
                $closure = $call->args[0]->value;
                foreach ($closure->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Expression &&
                        $stmt->expr instanceof Node\Expr\StaticCall &&
                        $this->isDeprecationCall($stmt->expr, 'notice') &&
                        $this->hasRenamingMessage($stmt->expr)) {
                        return true;
                    }
                }
            }
        }
        
        // Check direct calls
        return $this->hasRenamingMessage($call);
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
                
                // Also check if the target namespace is mentioned in any string arguments
                foreach ($expr->args as $arg) {
                    if ($this->containsTargetNamespace($arg)) {
                        return true;
                    }
                }
            }
            
            // Handle direct Deprecation::notice calls  
            if ($expr instanceof Node\Expr\StaticCall &&
                $this->isDeprecationCall($expr, 'notice')) {
                return $this->hasRenamingMessage($expr) || $this->containsTargetNamespaceInCall($expr);
            }
            
            // Handle other deprecation method calls like noticeWithNoReplacement
            if ($expr instanceof Node\Expr\StaticCall &&
                $this->isDeprecationMethodCall($expr)) {
                return $this->hasRenamingMessage($expr) || $this->containsTargetNamespaceInCall($expr);
            }
        }
        
        return false;
    }
    
    private function containsTargetNamespace(Node\Arg $arg): bool
    {
        if ($arg->value instanceof Node\Expr\Closure) {
            foreach ($arg->value->stmts as $stmt) {
                if ($this->statementContainsTargetNamespace($stmt)) {
                    return true;
                }
            }
        }
        return false;
    }
    
    private function statementContainsTargetNamespace(Node\Stmt $stmt): bool
    {
        if ($stmt instanceof Node\Stmt\Expression && 
            $stmt->expr instanceof Node\Expr\StaticCall) {
            return $this->containsTargetNamespaceInCall($stmt->expr);
        }
        return false;
    }
    
    private function containsTargetNamespaceInCall(Node\Expr\StaticCall $call): bool
    {
        $targetNamespace = $this->config['target_namespace'] ?? '';
        $targetClass = $this->config['target_class'] ?? '';
        
        foreach ($call->args as $arg) {
            if ($arg->value instanceof Node\Scalar\String_) {
                $message = $arg->value->value;
                // Check if the message contains our target namespace or class
                if (str_contains($message, $targetNamespace) || 
                    str_contains($message, $targetClass) ||
                    str_contains($message, $this->sourceNamespace) ||
                    str_contains($message, $this->sourceClassName)) {
                    return true;
                }
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