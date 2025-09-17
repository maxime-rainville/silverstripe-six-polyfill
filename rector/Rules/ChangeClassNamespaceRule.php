<?php

namespace SilverStripePolyfill\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symfony\Component\Yaml\Yaml;

/**
 * Change class namespace and name based on CMS 6 equivalence mapping from YAML config
 */
final class ChangeClassNamespaceRule extends AbstractRector
{
    private array $classMappings = [];
    private array $namespaceMappings = [];

    public function __construct()
    {
        $this->loadMappingsFromConfig();
    }

    private function loadMappingsFromConfig(): void
    {
        $configPath = __DIR__ . '/../../cms6-equivalence.yml';
        
        if (!file_exists($configPath)) {
            return; // Silently skip if config not found
        }

        try {
            $config = Yaml::parseFile($configPath);
            
            if (!isset($config['classes'])) {
                return;
            }

            foreach ($config['classes'] as $sourceClass => $targetConfig) {
                // Extract source namespace and class name
                $sourceParts = explode('\\', $sourceClass);
                $sourceClassName = array_pop($sourceParts);
                $sourceNamespace = implode('\\', $sourceParts);

                $targetNamespace = $targetConfig['target_namespace'] ?? null;
                $targetClassName = $targetConfig['target_class'] ?? $sourceClassName;

                if ($targetNamespace && $sourceNamespace !== $targetNamespace) {
                    $this->namespaceMappings[$sourceNamespace] = $targetNamespace;
                }

                if ($sourceClassName !== $targetClassName) {
                    $this->classMappings[$sourceClassName] = $targetClassName;
                }
            }
        } catch (\Exception $e) {
            // Silently handle YAML parsing errors
        }
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Change class namespace and name for CMS 6 polyfill compatibility based on cms6-equivalence.yml',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
namespace SilverStripe\ORM;

class ArrayList
{
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
namespace SilverStripe\Model\List;

class ArrayList
{
}
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
        return [Namespace_::class, Class_::class];
    }

    /**
     * @param Namespace_|Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Namespace_) {
            return $this->refactorNamespace($node);
        }

        if ($node instanceof Class_) {
            return $this->refactorClass($node);
        }

        return null;
    }

    private function refactorNamespace(Namespace_ $namespace): ?Namespace_
    {
        if ($namespace->name === null) {
            return null;
        }

        $currentNamespace = $namespace->name->toString();
        
        // Check for exact namespace match
        if (isset($this->namespaceMappings[$currentNamespace])) {
            $namespace->name = new Name($this->namespaceMappings[$currentNamespace]);
            return $namespace;
        }

        // Check for partial namespace matches (for nested namespaces)
        foreach ($this->namespaceMappings as $oldNamespace => $newNamespace) {
            if (str_starts_with($currentNamespace, $oldNamespace . '\\')) {
                $suffix = substr($currentNamespace, strlen($oldNamespace));
                $namespace->name = new Name($newNamespace . $suffix);
                return $namespace;
            }
        }

        return null;
    }

    private function refactorClass(Class_ $class): ?Class_
    {
        if ($class->name === null) {
            return null;
        }

        $currentClassName = $class->name->toString();
        
        if (isset($this->classMappings[$currentClassName])) {
            $class->name = new Node\Identifier($this->classMappings[$currentClassName]);
            return $class;
        }

        return null;
    }
}