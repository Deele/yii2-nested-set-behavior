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

use yii\db\ActiveQuery;

/**
 * @author Wanderson Bragança <wanderson.wbc@gmail.com>
 */
class NestedSetQuery extends ActiveQuery
{

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class' => NestedSetQueryBehavior::class,
            ]
        ];
    }
}
