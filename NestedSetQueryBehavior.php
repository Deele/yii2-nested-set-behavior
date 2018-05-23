<?php
/**
 * @link https://github.com/wbraganca/yii2-nested-set-behavior
 * @copyright Copyright (c) 2014 Wanderson Bragança
 * @license http://opensource.org/licenses/BSD-3-Clause
 *
 * @author Wanderson Bragança (wbraganca)
 * @author Nils (Deele) <deele@tuta.io>
 */

namespace wbraganca\behaviors;

use yii\base\Behavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * @author Wanderson Bragança <wanderson.wbc@gmail.com>
 */
class NestedSetQueryBehavior extends Behavior
{
    /**
     * @var ActiveQuery the owner of this behavior.
     */
    public $owner;

    /**
     * Gets root node(s).
     *
     * @return ActiveQuery|ActiveRecord
     */
    public function roots()
    {
        /** @var $modelClass ActiveRecord */
        $modelClass = $this->owner->modelClass;
        $model = new $modelClass;
        $this->owner->andWhere(
            $modelClass::getDb()
                       ->quoteColumnName($model->leftAttribute) . '=1'
        );
        unset($model);
        return $this->owner;
    }

    /**
     * @param array $settings
     *      - int|NestedSetTrait root
     *      - null level
     *      - null|callable queryCallback
     *      - int idAttribute
     *      - int titleAttribute
     *      - int subtractLevel
     *      - string spacer
     *      - string spacerArrow
     *
     * @return array
     */
    public function options($settings = []) {
        $root = ArrayHelper::getValue(
            $settings,
            'root',
            0
        );
        $level = ArrayHelper::getValue(
            $settings,
            'level',
            null
        );
        $queryCallback = ArrayHelper::getValue(
            $settings,
            'queryCallback',
            null
        );
        $titleCallback = ArrayHelper::getValue(
            $settings,
            'titleCallback',
            null
        );
        $idAttribute = ArrayHelper::getValue(
            $settings,
            'idAttribute',
            (is_object($root) ? $root->idAttribute : null)
        );
        $titleAttribute = ArrayHelper::getValue(
            $settings,
            'titleAttribute',
            (is_object($root) ? $root->titleAttribute : null)
        );
        $subtractLevel = ArrayHelper::getValue(
            $settings,
            'subtractLevel',
            0
        );
        $spacer = ArrayHelper::getValue(
            $settings,
            'spacer',
            '—'
        );
        $spacerArrow = ArrayHelper::getValue(
            $settings,
            'spacerArrow',
            '›'
        );
        $res = [];
        if (is_object($root)) {

            /**
             * @var NestedSetBehavior $root
             */
            $spacerLevel = $root->{$root->levelAttribute} - 1 - $subtractLevel;
            if ($spacerLevel >= 0) {
                $title = str_repeat(
                    $spacer,
                    $spacerLevel
                );
                if (($root->{$root->levelAttribute} - $subtractLevel) > 1) {
                    $title .= $spacerArrow;
                }
                if (is_callable($titleCallback)) {
                    $title = call_user_func(
                        $titleCallback,
                        $title,
                        $root
                    );
                }
                else {
                    $title .= $root->{$titleAttribute};
                }
                $res[$root->{$idAttribute}] = $title;
            }

            $query = $root->children();
            if (is_callable($queryCallback)) {
                $query = call_user_func(
                    $queryCallback,
                    $query
                );
            }
            foreach ($query->all() as $childRoot) {
                $settings['root'] = $childRoot;
                $settings['level'] = (!is_null($level) ? $level - 1 : $level);
                $res += $this->options($settings);
            }
        } elseif (is_scalar($root)) {
            if ($root == 0) {
                $query = $this->roots();
                if (is_callable($queryCallback)) {
                    $query = call_user_func(
                        $queryCallback,
                        $query
                    );
                }
                foreach ($query->all() as $rootItem) {
                    $settings['root'] = $rootItem;
                    $settings['level'] = (!is_null($level) ? $level - 1 : $level);
                    $res += $this->options($settings);
                }
            } else {
                $modelClass = $this->owner->modelClass;

                /**
                 * @var ActiveRecord $modelClass
                 */
                $model = new $modelClass;
                $query = $modelClass
                    ::find()
                    ->andWhere(
                        [
                            $model->idAttribute => $root
                        ]
                    );
                if (is_callable($queryCallback)) {
                    $query = call_user_func(
                        $queryCallback,
                        $query
                    );
                }
                $root = $query->one();
                if ($root) {
                    $settings['root'] = $root;
                    $settings['level'] = $level;
                    $res += $this->options($settings);
                }
                unset($model);
            }
        }

        return $res;
    }

    /**
     * @param int $root
     * @param null $level
     * @param null|callable $queryCallback
     *
     * @return array
     */
    public function dataFancytree(
        $root = 0,
        $level = null,
        $queryCallback = null
    ) {
        $data = array_values(
            $this->prepareData2Fancytree(
                $root,
                $level,
                $queryCallback
            )
        );
        return $this->makeData2Fancytree($data);
    }

    /**
     * @param int $root
     * @param null $level
     * @param null|callable $queryCallback
     *
     * @return array
     */
    private function prepareData2Fancytree(
        $root = 0,
        $level = null,
        $queryCallback = null
    ) {
        $res = [];
        if (is_object($root)) {

            /**
             * @var NestedSetBehavior $root
             */
            $res[$root->{$root->idAttribute}] = [
                'key' => $root->{$root->idAttribute},
                'title' => $root->{$root->titleAttribute}
            ];

            $query = $root->children();
            if (is_callable($queryCallback)) {
                $query = call_user_func(
                    $queryCallback,
                    $query
                );
            }
            if ($level) {
                foreach ($query->all() as $childRoot) {
                    $aux = $this->prepareData2Fancytree(
                        $childRoot,
                        $level - 1
                    );

                    if (isset($res[$root->{$root->idAttribute}]['children']) && !empty($aux)) {
                        $res[$root->{$root->idAttribute}]['folder'] = true;
                        $res[$root->{$root->idAttribute}]['children'] += $aux;

                    } elseif (!empty($aux)) {
                        $res[$root->{$root->idAttribute}]['folder'] = true;
                        $res[$root->{$root->idAttribute}]['children'] = $aux;
                    }
                }
            } elseif (is_null($level)) {
                foreach ($query->all() as $childRoot) {
                    $aux = $this->prepareData2Fancytree(
                        $childRoot,
                        null
                    );
                    if (isset($res[$root->{$root->idAttribute}]['children']) && !empty($aux)) {
                        $res[$root->{$root->idAttribute}]['folder'] = true;
                        $res[$root->{$root->idAttribute}]['children'] += $aux;

                    } elseif (!empty($aux)) {
                        $res[$root->{$root->idAttribute}]['folder'] = true;
                        $res[$root->{$root->idAttribute}]['children'] = $aux;
                    }
                }
            }
        } elseif (is_scalar($root)) {
            if ($root == 0) {
                $query = $this->roots();
                if (is_callable($queryCallback)) {
                    $query = call_user_func(
                        $queryCallback,
                        $query
                    );
                }
                foreach ($query->all() as $rootItem) {
                    if ($level) {
                        $res += $this->prepareData2Fancytree(
                            $rootItem,
                            $level - 1,
                            $queryCallback
                        );
                    } elseif (is_null($level)) {
                        $res += $this->prepareData2Fancytree(
                            $rootItem,
                            null,
                            $queryCallback
                        );
                    }
                }
            } else {
                $modelClass = $this->owner->modelClass;

                /**
                 * @var ActiveRecord $modelClass
                 */
                $model = new $modelClass;
                $query = $modelClass::find()
                                    ->andWhere([$model->idAttribute => $root]);
                if (is_callable($queryCallback)) {
                    $query = call_user_func(
                        $queryCallback,
                        $query
                    );
                }
                $root = $query->one();
                if ($root) {
                    $res += $this->prepareData2Fancytree(
                        $root,
                        $level,
                        $queryCallback
                    );
                }
                unset($model);
            }
        }
        return $res;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    private function makeData2Fancytree(&$data)
    {
        $tree = [];
        foreach ($data as $key => &$item) {
            if (isset($item['children'])) {
                $item['children'] = array_values($item['children']);
                $tree[$key] = $this->makeData2Fancytree($item['children']);
            }
            $tree[$key] = $item;
        }
        return $tree;
    }
}
