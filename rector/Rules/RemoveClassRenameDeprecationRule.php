<?php

namespace SilverStripePolyfill\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Expression;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;

/**
 * Remove Deprecation::notice calls that are specifically about class renaming
 */
final class RemoveClassRenameDeprecationRule extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove Deprecation::notice calls about class renaming for CMS 6 polyfill',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
Deprecation::notice('5.4.0', 'Will be renamed to SilverStripe\Model\List\ArrayList', Deprecation::SCOPE_CLASS);
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

        // Check if this is a Deprecation::notice call
        if (!$this->isName($call->name, 'notice')) {
            return null;
        }

        // Check if it's a static call to Deprecation class
        if ($call instanceof StaticCall && !$this->isName($call->class, 'Deprecation')) {
            return null;
        }

        // Check if the call contains "Will be renamed" in the message
        if (isset($call->args[1]) && $call->args[1]->value instanceof Node\Scalar\String_) {
            $message = $call->args[1]->value->value;
            if (str_contains($message, 'Will be renamed')) {
                // Remove this deprecation notice
                $this->removeNode($node);
                return null;
            }
        }

        return null;
    }
}