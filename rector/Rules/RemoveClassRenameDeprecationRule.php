<?php

namespace SilverStripePolyfill\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Expression;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symfony\Component\Yaml\Yaml;

/**
 * Remove Deprecation::notice calls that are specifically about class renaming
 */
final class RemoveClassRenameDeprecationRule extends AbstractRector
{
    private array $classConfigs = [];

    public function __construct()
    {
        $this->loadClassConfigs();
    }

    private function loadClassConfigs(): void
    {
        $configPath = __DIR__ . '/../../cms6-equivalence.yml';
        
        if (!file_exists($configPath)) {
            return;
        }

        try {
            $config = Yaml::parseFile($configPath);
            if (isset($config['classes'])) {
                $this->classConfigs = $config['classes'];
            }
        } catch (\Exception $e) {
            // Silently handle YAML parsing errors
        }
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove Deprecation::notice calls about class renaming for CMS 6 polyfill',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
Deprecation::withSuppressedNotice(function () {
    Deprecation::notice('5.4.0', 'Will be renamed to SilverStripe\Model\List\ArrayList', Deprecation::SCOPE_CLASS);
});
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
// Removed
CODE_SAMPLE
                )
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    /**
     * @param Expression $node
     */
    public function refactor(Node $node): ?Node
    {
        if (!$node->expr instanceof StaticCall && !$node->expr instanceof MethodCall) {
            return null;
        }

        $call = $node->expr;

        // Check if this is a Deprecation call
        if (!$this->isDeprecationCall($call)) {
            return null;
        }

        // Check if it's about class renaming/moving
        if ($this->isAboutClassRenaming($call)) {
            $this->removeNode($node);
            return null;
        }

        return null;
    }

    private function isDeprecationCall($call): bool
    {
        if (!$call instanceof StaticCall) {
            return false;
        }

        return $this->isName($call->class, 'Deprecation') && 
               $this->isNames($call->name, ['notice', 'withSuppressedNotice', 'noticeWithNoReplacement', 'noticeWithNoReplacment']);
    }

    private function isAboutClassRenaming($call): bool
    {
        // Handle withSuppressedNotice with closure
        if ($this->isName($call->name, 'withSuppressedNotice')) {
            if (isset($call->args[0]) && $call->args[0]->value instanceof Node\Expr\Closure) {
                $closure = $call->args[0]->value;
                foreach ($closure->stmts as $stmt) {
                    if ($stmt instanceof Expression && 
                        $stmt->expr instanceof StaticCall &&
                        $this->isDeprecationCall($stmt->expr) &&
                        $this->hasRenamingMessage($stmt->expr)) {
                        return true;
                    }
                }
            }
        }

        // Handle direct deprecation calls
        return $this->hasRenamingMessage($call);
    }

    private function hasRenamingMessage($call): bool
    {
        // First check for common renaming patterns regardless of config
        foreach ($call->args as $arg) {
            if ($arg->value instanceof Node\Scalar\String_) {
                $message = $arg->value->value;
                
                // Check for common renaming phrases
                if (str_contains($message, 'Will be renamed') || 
                    str_contains($message, 'renamed to') ||
                    str_contains($message, 'Will be moved to') ||
                    str_contains($message, 'moved to')) {
                    return true;
                }
            }
        }
        
        // Get target namespaces from our config
        $targetNamespaces = [];
        $sourceNamespaces = [];
        
        foreach ($this->classConfigs as $sourceClass => $config) {
            $targetNamespaces[] = $config['target_namespace'] ?? '';
            $targetNamespaces[] = $config['target_class'] ?? '';
            $sourceNamespaces[] = $sourceClass;
            
            // Extract source namespace
            $parts = explode('\\', $sourceClass);
            array_pop($parts); // Remove class name
            $sourceNamespaces[] = implode('\\', $parts);
        }

        foreach ($call->args as $arg) {
            if ($arg->value instanceof Node\Scalar\String_) {
                $message = $arg->value->value;
                
                // Check if message contains any of our target or source namespaces
                foreach (array_merge($targetNamespaces, $sourceNamespaces) as $namespace) {
                    if (!empty($namespace) && str_contains($message, $namespace)) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
}