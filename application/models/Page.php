<?php

namespace app\models;

use app\behaviors\CleanRelations;
use app\behaviors\Tree;
use app\properties\HasProperties;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "page".
 *
 * @property integer $id
 * @property integer $parent_id
 * @property string $slug
 * @property string $slug_compiled
 * @property boolean $slug_absolute
 * @property boolean $published
 * @property boolean $searchable
 * @property integer $robots
 * @property string $title
 * @property string $h1
 * @property string $meta_description
 * @property string $breadcrumbs_label
 * @property string $content
 * @property string $announce
 * @property integer $sort_order
 * @property string $date_added
 * @property string $date_modified
 * @property integer $is_deleted
 * @property string $show_type
 */
class Page extends ActiveRecord
{
    private static $identity_map = [];

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%page}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['slug', 'title'], 'required'],
            [['robots', 'is_deleted', 'parent_id', 'sort_order'], 'integer'],
            [['slug_absolute', 'published', 'searchable'], 'boolean'],
            [
                ['content', 'title', 'h1', 'meta_description', 'breadcrumbs_label', 'announce', 'slug_compiled'],
                'string'
            ],
            [['date_added', 'date_modified'], 'safe'],
            [['slug'], 'string', 'max' => 80],
            [['slug_compiled'], 'string', 'max' => 180],
            [['show_type'], 'string'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'slug' => Yii::t('app', 'Slug'),
            'slug_compiled' => Yii::t('app', 'Slug Compiled'),
            'slug_absolute' => Yii::t('app', 'Slug Absolute'),
            'content' => Yii::t('app', 'Content'),
            'published' => Yii::t('app', 'Published'),
            'searchable' => Yii::t('app', 'Searchable'),
            'show_type' => Yii::t('app', 'Show Type'),
            'robots' => Yii::t('app', 'Robots'),
            'title' => Yii::t('app', 'Title'),
            'h1' => Yii::t('app', 'H1'),
            'meta_description' => Yii::t('app', 'Meta Description'),
            'breadcrumbs_label' => Yii::t('app', 'Breadcrumbs Label'),
            'announce' => Yii::t('app', 'Announce'),
            'sort_order' => Yii::t('app', 'Sort Order'),
            'date_added' => Yii::t('app', 'Date Added'),
            'date_modified' => Yii::t('app', 'Date Modified'),
            'is_deleted' => Yii::t('app', 'Is Deleted'),
        ];
    }

    public function behaviors()
    {
        return [
            [
                'class' => HasProperties::className(),
            ],
            [
                'class' => \devgroup\TagDependencyHelper\ActiveRecordHelper::className(),
            ],
            [
                'class' => CleanRelations::className(),
            ],
            [
                'class' => Tree::className(),
            ],
        ];
    }


    /**
     * Search tasks
     * @param $params
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        /** @var $query \yii\db\ActiveQuery */
        $query = self::find();
        if (null != $this->parent_id) {
            $query->andWhere(['parent_id' => $this->parent_id]);
        }
        $dataProvider = new ActiveDataProvider(
            [
                'query' => $query,
                'pagination' => [
                    'pageSize' => 10,
                ],
            ]
        );
        if (!($this->load($params))) {
            return $dataProvider;
        }
        $query->andFilterWhere(['id' => $this->id]);
        $query->andFilterWhere(['like', 'slug', $this->slug]);
        $query->andFilterWhere(['like', 'slug_compiled', $this->slug_compiled]);
        $query->andFilterWhere(['like', 'slug_absolute', $this->slug_absolute]);
        $query->andFilterWhere(['published' => $this->published]);
        $query->andFilterWhere(['like', 'title', $this->title]);
        $query->andFilterWhere(['like', 'h1', $this->h1]);
        $query->andFilterWhere(['like', 'meta_description', $this->meta_description]);
        $query->andFilterWhere(['like', 'breadcrumbs_label', $this->breadcrumbs_label]);
        $query->andFilterWhere(['is_deleted' => $this->is_deleted]);
        return $dataProvider;
    }

    public static function findById($id, $is_published = 1)
    {
        if (!isset(static::$identity_map[$id])) {

            $cacheKey = "Page:$id:$is_published";
            static::$identity_map[$id] = Yii::$app->cache->get($cacheKey);
            if (!is_object(static::$identity_map[$id])) {
                static::$identity_map[$id] = Page::findOne(['id' => $id, 'published' => $is_published]);

                if (is_object(static::$identity_map[$id])) {
                    Yii::$app->cache->set(
                        $cacheKey,
                        static::$identity_map[$id],
                        86400,
                        new \yii\caching\TagDependency([
                            'tags' => [
                                \devgroup\TagDependencyHelper\ActiveRecordHelper::getCommonTag(static::className())
                            ]
                        ])
                    );
                }
            }
        }
        return static::$identity_map[$id];
    }

    public function beforeSave($insert)
    {
        if (!isset($this->date_added)) {
            $this->date_added = date('Y-m-d H:i:s');
        }
        $this->date_modified = date('Y-m-d H:i:s');
        $this->slug_compiled = $this->compileSlug();

        if (1 === $this->is_deleted) {
            $this->published = 0;
        }

        \yii\caching\TagDependency::invalidate(
            Yii::$app->cache,
            [
                \devgroup\TagDependencyHelper\ActiveRecordHelper::getCommonTag($this->className()),
                'Page:'.$this->slug_compiled
            ]
        );

        \yii\caching\TagDependency::invalidate(
            Yii::$app->cache,
            [
                \devgroup\TagDependencyHelper\ActiveRecordHelper::getCommonTag($this->className()),
                'Page:'.$this->id.':0'
            ]
        );

        \yii\caching\TagDependency::invalidate(
            Yii::$app->cache,
            [
                \devgroup\TagDependencyHelper\ActiveRecordHelper::getCommonTag($this->className()),
                'Page:'.$this->id.':1'
            ]
        );

        if (empty($this->breadcrumbs_label)) {
            $this->breadcrumbs_label = $this->title;
        }

        if (empty($this->h1)) {
            $this->h1 = $this->title;
        }



        return parent::beforeSave($insert);
    }

    public function compileSlug()
    {
        if($this->slug_absolute == 1){
            return 'http://' . $this->slug . '.' . Config::getValue('core.serverName', Yii::$app->request->serverName) . '/';
        }
        $url_parts = [$this->slug];
        $parent_model = $this->parent;
        while ($parent_model !== null) {
            if ($parent_model->slug == ':mainpage:') {
                break;
            }
            if($parent_model->slug_absolute == 1){
                $url_parts[] = 'http://' . $parent_model->slug . '.' . Config::getValue('core.serverName', Yii::$app->request->serverName);
                break;
            }
            $url_parts[] = $parent_model->slug;
            $parent_model = $parent_model->parent;
        }
        $url_parts = array_reverse($url_parts);

        return implode('/', $url_parts);
    }

    /**
     * @param string $path PATH из запроса ( $request->getPathInfo() )
     * @return mixed|null
     */
    public static function getByUrlPath($path = null)
    {
        if ((null === $path) || !is_string($path)) {
            return null;
        }
        $cacheKey = "Page:$path";
        $page = Yii::$app->cache->get($cacheKey);
        if ($page === false) {
            $page = static::find()->where(['slug_compiled' => $path, 'published' => 1])->asArray()->one();
            $duration = 86400;
            if (!is_array($page)) {
                $duration = 3600;
            }
            Yii::$app->cache->set(
                $cacheKey,
                $page,
                $duration,
                new \yii\caching\TagDependency([
                    'tags' => [
                        \devgroup\TagDependencyHelper\ActiveRecordHelper::getCommonTag(static::className())
                    ]
                ])
            );
        }
        return $page;
    }

    /**
     * Первое удаление в корзину, второе из БД
     *
     * @return bool
     */
    public function beforeDelete()
    {
        if (null !== $children = static::find()->where(['parent_id' => $this->id])->all()) {
            foreach ($children as $child) {
                $child->delete();
            }
        }
        $result = parent::beforeDelete();
        if (0 === intval($this->is_deleted)) {
            $this->is_deleted = 1;
            $this->save();

            return false;
        }
        return $result;
    }

    /**
     * Отмена удаления объекта
     *
     * @return bool Restore result
     */
    public function restoreFromTrash()
    {
        $this->is_deleted = 0;
        return $this->save();
    }
}
