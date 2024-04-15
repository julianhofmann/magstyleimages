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
     * @return mixed
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        $value = $renderChildrenClosure();
        return static::calculateAction($value, $arguments);
    }

    /**
     * @param float|integer|string|iterable|array $value
     * @return float|array
     */
    protected static function calculateAction($value, array $arguments = [])
    {
        if (!is_scalar($value)) {
            return 0;
        }
        /** @var integer|float $a */
        return round($value, $arguments['decimals']);
    }
}
