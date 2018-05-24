<?php
/**
 * @link https://github.com/wbraganca/yii2-nested-set-behavior
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace wbraganca\behaviors;

use Yii;
use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * Class NestedSetTrait
 *
 * @property NestedSetBehavior $nestedSet {@link NestedSetTrait::getNestedSet()}
 *
 * @author Nils (Deele) <deele@tuta.io>
 *
 * @package wbraganca\yii2-nested-set-behavior
 */
trait NestedSetTrait
{

    /**
     * @var string|array|null Value of this attribute triggers different NestedSetBehavior actions providing control
     * over entry position within nested set.
     */
    public $positionInTree = null;

    /**
     * @return null|NestedSetBehavior
     */
    public function getNestedSet()
    {

        /**
         * @var ActiveRecord|NestedSetTrait $this
         */
        return $this->getBehavior('nestedSet');
    }

    /**
     * @return NestedSetQuery|NestedSetQueryBehavior
     */
    public static function find()
    {
        return new NestedSetQuery(get_called_class());
    }

    /**
     * @return boolean whether the deletion is successful.
     */
    public function deleteNode()
    {
        return $this->nestedSet->deleteNode();
    }

    /**
     * @inheritdoc
     * @return bool
     */
    public function save($runValidation = true, $attributeNames = null)
    {
        $nestedSet = $this->getNestedSet();
        $positionInTree = $this->positionInTree;

        /**
         * @var ActiveRecord $this
         */
        if (is_null($positionInTree)) {
            return $nestedSet->saveNode($runValidation, $attributeNames);
        } else {

            // Prepare position
            if (is_string($positionInTree)) {

                // Try to decode from json string
                try {
                    $position = Json::decode($positionInTree);
                } catch (\Exception $e) {
                    $this->addError(
                        'positionInTree',
                        Yii::t(
                            'common.base.NestedSetTrait',
                            'Invalid JSON string'
                        )
                    );

                    return false;
                }
            } elseif (is_array($positionInTree)) {
                $position = $positionInTree;
            } else {
                $this->addError(
                    'positionInTree',
                    Yii::t(
                        'common.base.NestedSetTrait',
                        'Invalid format, valid JSON string expected'
                    )
                );

                return false;
            }

            // Prepare position action
            if (array_key_exists('action', $position) || array_key_exists(0, $position)) {
                if (array_key_exists('action', $position)) {
                    $action = $position['action'];
                } else {
                    $action = $position[0];
                }
            } else {
                $this->addError(
                    'positionInTree',
                    Yii::t(
                        'common.base.NestedSetTrait',
                        'Invalid action'
                    )
                );

                return false;
            }

            // Do nothing if no action was provided
            if (is_null($action) || strlen($action) == 0) {
                return $nestedSet->saveNode($runValidation, $attributeNames);
            }

            // Prepare target
            $targetId = null;
            $target   = null;
            if (array_key_exists('target', $position) || array_key_exists(1, $position)) {
                if (array_key_exists('target', $position)) {
                    $targetId = $position['target'];
                } else {
                    $targetId = $position[1];
                }
            }

            if ($action != 'moveAsRoot' && $action != 'createRoot') {

                // Validate target
                if (is_null($targetId) || (int) $targetId == 0) {
                    $this->addError(
                        'positionInTree',
                        Yii::t(
                            'common.base.NestedSetTrait',
                            'Invalid target'
                        )
                    );

                    return false;
                }

                // Find out if target exists
                $target = $this->findOne(['id' => $targetId]);
                if (is_null($target)) {
                    $this->addError(
                        'positionInTree',
                        Yii::t(
                            'common.base.NestedSetTrait',
                            'Requested target not found'
                        )
                    );

                    return false;
                }
            }

            /**
             * Evaluate action
             *
             * @var ActiveRecord $target
             */
            if ($this->isNewRecord) {
                switch ($action) {
                    case 'createRoot':
                        return $nestedSet->saveNode($runValidation, $attributeNames);
                        break;
                    case 'prependTo':
                        return $nestedSet->prependTo($target, $runValidation, $attributeNames);
                        break;
                    case 'appendTo':
                        return $nestedSet->appendTo($target, $runValidation, $attributeNames);
                        break;
                    case 'insertBefore':
                        return $nestedSet->insertBefore($target, $runValidation, $attributeNames);
                        break;
                    case 'insertAfter':
                        return $nestedSet->insertAfter($target, $runValidation, $attributeNames);
                        break;
                    case 'moveBefore':
                    case 'moveAfter':
                    case 'moveAsFirst':
                    case 'moveAsLast':
                    case 'moveAsRoot':
                        $this->addError(
                            'positionInTree',
                            Yii::t(
                                'common.base.NestedSetTrait',
                                'Requested action not available for new record'
                            )
                        );

                        return false;
                        break;
                    default:
                        $this->addError(
                            'positionInTree',
                            Yii::t(
                                'common.base.NestedSetTrait',
                                'Invalid action'
                            )
                        );

                        return false;
                }
            } else {
                if ($nestedSet->saveNode($runValidation, $attributeNames) === false) {
                    return false;
                }
                switch ($action) {
                    case 'moveBefore':
                        return $nestedSet->moveBefore($target);
                        break;
                    case 'moveAfter':
                        return $nestedSet->moveAfter($target);
                        break;
                    case 'moveAsFirst':
                        return $nestedSet->moveAsFirst($target);
                        break;
                    case 'moveAsLast':
                        return $nestedSet->moveAsLast($target);
                        break;
                    case 'moveAsRoot':
                        return $nestedSet->moveAsRoot();
                        break;
                    case 'createRoot':
                    case 'prependTo':
                    case 'appendTo':
                    case 'insertBefore':
                    case 'insertAfter':
                        $this->addError(
                            'positionInTree',
                            Yii::t(
                                'common.base.NestedSetTrait',
                                'Requested action not available for existing record'
                            )
                        );

                        return false;
                        break;
                    default:
                        $this->addError(
                            'positionInTree',
                            Yii::t(
                                'common.base.NestedSetTrait',
                                'Invalid action'
                            )
                        );

                        return false;
                }
            }
        }
    }

    /**
     * @param bool $treeView
     *
     * @return array
     */
    public function childrenTree($treeView = false)
    {
        $data = [];

        /**
         * @var ActiveRecord|NestedSetTrait $this
         */
        $tree = $this
            ->find()
            ->dataFancytree();
        if ($treeView) {
            return $tree[0]['children'];
        }
        else {
            $this->convertFancyTreeToTreeViewData(
                $tree[0]['children'],
                $tree[0]['children'],
                $data
            );

            return $data;
        }
    }

    /**
     * @param array $children
     * @param array $family
     * @param array $data
     */
    protected function convertFancyTreeToTreeViewData($children, $family, &$data) {
        foreach ($children as $categoryData) {
            $datum = [
                'href' => $categoryData['key'],
                'text' => $categoryData['title'],
                'selectable' => false,
                'tags' => [],
                'state' => []
            ];
            if (isset($family[$categoryData['key']])) {
                $datum['state']['checked'] = true;
                if ($family[$categoryData['key']] == 1) {
                    $datum['tags'][] = 'Is main';
                }
            }
            if (isset($categoryData['children'])) {
                $nodes = [];
                $this->convertFancyTreeToTreeViewData(
                    $categoryData['children'],
                    $family,
                    $nodes
                );
                $datum['nodes'] = $nodes;
            }
            $data[] = $datum;
        }
    }
}
