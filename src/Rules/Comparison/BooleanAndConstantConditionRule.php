<?php declare(strict_types = 1);

namespace PHPStan\Rules\Comparison;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\BooleanAndNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantBooleanType;
use function count;
use function sprintf;

/**
 * @implements Rule<BooleanAndNode>
 */
class BooleanAndConstantConditionRule implements Rule
{

	public function __construct(
		private ConstantConditionRuleHelper $helper,
		private bool $treatPhpDocTypesAsCertain,
	)
	{
	}

	public function getNodeType(): string
	{
		return BooleanAndNode::class;
	}

	public function processNode(
		Node $node,
		Scope $scope,
	): array
	{
		$errors = [];
		$originalNode = $node->getOriginalNode();
		$leftType = $this->helper->getBooleanType($scope, $originalNode->left);
		$tipText = 'Because the type is coming from a PHPDoc, you can turn off this check by setting <fg=cyan>treatPhpDocTypesAsCertain: false</> in your <fg=cyan>%configurationFile%</>.';
		if ($leftType instanceof ConstantBooleanType) {
			$addTipLeft = function (RuleErrorBuilder $ruleErrorBuilder) use ($scope, $tipText, $originalNode): RuleErrorBuilder {
				if (!$this->treatPhpDocTypesAsCertain) {
					return $ruleErrorBuilder;
				}

				$booleanNativeType = $this->helper->getNativeBooleanType($scope, $originalNode->left);
				if ($booleanNativeType instanceof ConstantBooleanType) {
					return $ruleErrorBuilder;
				}

				return $ruleErrorBuilder->tip($tipText);
			};
			$errors[] = $addTipLeft(RuleErrorBuilder::message(sprintf(
				'Left side of && is always %s.',
				$leftType->getValue() ? 'true' : 'false',
			)))->line($originalNode->left->getLine())->build();
		}

		$rightScope = $node->getRightScope();
		$rightType = $this->helper->getBooleanType(
			$rightScope,
			$originalNode->right,
		);
		if ($rightType instanceof ConstantBooleanType) {
			$addTipRight = function (RuleErrorBuilder $ruleErrorBuilder) use ($rightScope, $originalNode, $tipText): RuleErrorBuilder {
				if (!$this->treatPhpDocTypesAsCertain) {
					return $ruleErrorBuilder;
				}

				$booleanNativeType = $this->helper->getNativeBooleanType(
					$rightScope->doNotTreatPhpDocTypesAsCertain(),
					$originalNode->right,
				);
				if ($booleanNativeType instanceof ConstantBooleanType) {
					return $ruleErrorBuilder;
				}

				return $ruleErrorBuilder->tip($tipText);
			};

			if (!$scope->isInFirstLevelStatement()) {
				$errors[] = $addTipRight(RuleErrorBuilder::message(sprintf(
					'Right side of && is always %s.',
					$rightType->getValue() ? 'true' : 'false',
				)))->line($originalNode->right->getLine())->build();
			}
		}

		if (count($errors) === 0) {
			$nodeType = $scope->getType($originalNode);
			if ($nodeType instanceof ConstantBooleanType) {
				$addTip = function (RuleErrorBuilder $ruleErrorBuilder) use ($scope, $originalNode, $tipText): RuleErrorBuilder {
					if (!$this->treatPhpDocTypesAsCertain) {
						return $ruleErrorBuilder;
					}

					$booleanNativeType = $scope->doNotTreatPhpDocTypesAsCertain()->getType($originalNode);
					if ($booleanNativeType instanceof ConstantBooleanType) {
						return $ruleErrorBuilder;
					}

					return $ruleErrorBuilder->tip($tipText);
				};

				if (!$scope->isInFirstLevelStatement()) {
					$errors[] = $addTip(RuleErrorBuilder::message(sprintf(
						'Result of && is always %s.',
						$nodeType->getValue() ? 'true' : 'false',
					)))->build();
				}
			}
		}

		return $errors;
	}

}
