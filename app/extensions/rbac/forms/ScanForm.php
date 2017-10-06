<?php

namespace justcoded\yii2\rbac\forms;

use Yii;
use yii\base\Model;
use yii\rbac\Permission as RbacPermission;
use yii\rbac\Role as RbacRole;
use justcoded\yii2\rbac\helpers\ScanHelper;
use justcoded\yii2\rbac\models\Permission;

class ScanForm extends Model
{
	/**
	 * Path to scan.
	 *
	 * @var string
	 */
	public $path;

	/**
	 * Paths to ignore.
	 * Use comma to specify several paths.
	 *
	 * @var array|string
	 */
	public $ignorePath;

	/**
	 * Routes base prefix to be added to all found routes
	 *
	 * @var string
	 */
	public $routesBase;

	/**
	 * Internal items cache array to speed up some operations.
	 *
	 * @var RbacPermission[]|RbacRole[]
	 */
	protected $itemsCache;

	/**
	 * @inheritdoc
	 * @return array
	 */
	public function rules()
	{
		return [
			[['path'], 'required'],
			[['path', 'routesBase'], 'string'],
			[['routesBase'], 'default', 'value' => ''],

			[['path'], 'filter', 'filter' => function ($value) {
				return \Yii::getAlias($value);
			}],
			[['ignorePath'], 'default', 'value' => []],
			[['ignorePath'], 'filter', 'filter' => function ($value) {
				if (! is_array($value)) {
					$value = explode(',', trim($value));
				}
				return $value;
			}],

			[['path'], 'validDir'],
		];
	}

	/**
	 * Validate that passed value is a real directory path
	 *
	 * @param string $attribute
	 * @param array $params
	 * @param mixed $validator
	 *
	 * @return bool
	 */
	public function validDir($attribute, $params, $validator)
	{
		if (!is_dir($this->$attribute)) {
			$this->addError(
				$attribute,
				\Yii::t('app', '{attr} must be a directory.', [
					'attr' => $this->getAttributeLabel($attribute)
				])
			);
			return false;
		}
		return true;
	}

	/**
	 * Run routes scan
	 *
	 * @return array|false
	 */
	public function scan()
	{
		if (! $this->validate()) {
			return false;
		}

		$controllers = ScanHelper::scanControllers($this->path, $this->ignorePath);
		$actionRoutes = ScanHelper::scanControllerActionIds($controllers);

		if (empty($actionRoutes)) {
			$this->addError('path', 'Unable to find controllers/actions.');
			return false;
		}

		return $actionRoutes;
	}

	/**
	 * Import permissions with wildcards, if they have / inside.
	 *
	 * @param string[] $permissions
	 *
	 * @return array
	 */
	public function importPermissions(array $permissions)
	{
		$auth = Yii::$app->authManager;

		$inserted = [];
		foreach ($permissions as $route) {
			$route = $this->routesBase . $route;

			if (! $auth->getPermission($route)) {
				$wildcard = Permission::getWildcard($route, 'Route ');
				$permission = Permission::create($route, 'Route ' . $route, null, [$wildcard]);

				$inserted[$wildcard->name] = 1;
				$inserted[$permission->name] = 1;
			}
		}

		return $inserted;
	}
}
