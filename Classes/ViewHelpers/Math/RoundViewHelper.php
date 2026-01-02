<?php

declare(strict_types=1);
namespace Webenergy\Magstyleimages\ViewHelpers\Math;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithContentArgumentAndRenderStatic;

/**
 * Math: Round
 *
 * Rounds off $a which can be either an array-accessible
 * value (Iterator+ArrayAccess || array) or a raw numeric
 * value.
 */
class RoundViewHelper extends AbstractViewHelper
{
    use CompileWithContentArgumentAndRenderStatic;

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('a', 'mixed', 'First number for calculation');
        $this->registerArgument('decimals', 'integer', 'Number of decimals', false, 0);
    }

    /**
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ): float|int {
        $value = $renderChildrenClosure();
        return static::calculateAction($value, $arguments);
    }

    /**
     * @param float|int|string|iterable|array $value
     * @param array $arguments
     * @return int|float
     */
    protected static function calculateAction($value, array $arguments = []): int|float
    {
        if (!is_scalar($value)) {
            return 0;
        }

        /** @var int|float|string $value */
        return round((float)$value, $arguments['decimals']);
    }
}
