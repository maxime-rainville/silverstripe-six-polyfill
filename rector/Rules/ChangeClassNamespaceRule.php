<?php

namespace SilverStripePolyfill\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;

/**
 * Change class namespace and name based on CMS 6 equivalence mapping
 */
final class ChangeClassNamespaceRule extends AbstractRector
{
    private array $namespaceMapping = [];
    private array $classMapping = [];

    public function __construct()
    {
        // Define the namespace and class mappings for CMS 6
        $this->namespaceMapping = [
            'SilverStripe\\Forms' => 'SilverStripe\\Forms\\Validation',
            'SilverStripe\\ORM' => 'SilverStripe\\Model\\List',
            'SilverStripe\\View' => 'SilverStripe\\Model',
            'SilverStripe\\Security' => 'SilverStripe\\Security\\Validation',
        ];

        $this->classMapping = [
            'RequiredFields' => 'RequiredFieldsValidator',
            'ViewableData' => 'ModelData',
            'ViewableData_Customised' => 'ModelDataCustomised',
            'ViewableData_Debugger' => 'ModelDataDebugger',
            'SSViewer_BasicIteratorSupport' => 'BasicIteratorSupport',
            'PasswordValidator' => 'RulesPasswordValidator',
        ];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Change class namespace and name for CMS 6 polyfill compatibility',
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
        $newNamespace = $this->mapNamespace($currentNamespace);

        if ($newNamespace !== $currentNamespace) {
            $namespace->name = new Name($newNamespace);
            return $namespace;
        }

        return null;
    }

    private function refactorClass(Class_ $class): ?Class_
    {
        if ($class->name === null) {
            return null;
        }

        $currentClassName = $class->name->toString();
        $newClassName = $this->classMapping[$currentClassName] ?? $currentClassName;

        if ($newClassName !== $currentClassName) {
            $class->name = new Node\Identifier($newClassName);
            return $class;
        }

        return null;
    }

    private function mapNamespace(string $namespace): string
    {
        // Handle specific mappings
        foreach ($this->namespaceMapping as $old => $new) {
            if (str_starts_with($namespace, $old)) {
                // Handle special cases for different target namespaces
                if ($old === 'SilverStripe\\ORM') {
                    // ArrayLib goes to Core, others go to Model\List
                    if (str_contains($namespace, 'ArrayLib')) {
                        return str_replace($old, 'SilverStripe\\Core', $namespace);
                    }
                    if (str_contains($namespace, 'Validation')) {
                        return str_replace($old, 'SilverStripe\\Core\\Validation', $namespace);
                    }
                    return str_replace($old, 'SilverStripe\\Model\\List', $namespace);
                }
                
                if ($old === 'SilverStripe\\View') {
                    // Template classes go to TemplateEngine
                    if (str_contains($namespace, 'Template')) {
                        return str_replace($old, 'SilverStripe\\TemplateEngine', $namespace);
                    }
                    return str_replace($old, 'SilverStripe\\Model', $namespace);
                }
                
                return str_replace($old, $new, $namespace);
            }
        }

        return $namespace;
    }
}