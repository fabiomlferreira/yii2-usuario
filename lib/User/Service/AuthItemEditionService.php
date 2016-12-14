<?php
namespace Da\User\Service;

use Da\User\Contracts\ServiceInterface;
use Da\User\Factory\AuthItemFactory;
use Da\User\Model\AbstractAuthItem;
use Da\User\Traits\AuthManagerTrait;
use Da\User\Traits\ContainerTrait;
use Exception;

class AuthItemEditionService implements ServiceInterface
{
    use AuthManagerTrait;
    use ContainerTrait;

    protected $model;

    public function __construct(AbstractAuthItem $model)
    {
        $this->model = $model;
    }

    public function run()
    {
        if (!$this->model->validate()) {
            return false;
        }
        try {
            if ($this->model->getIsNewRecord()) {
                $item = AuthItemFactory::makeByType($this->model->getType(), $this->model->name);
            } else {
                $item = $this->model->item;
            }

            $item->name = $this->model->name;
            $item->description = $this->model->description;

            if (!empty($this->model->rule)) {
                $rule = $this->make($this->model->rule);
                if (null === $this->getAuthManager()->getRule($rule->name)) {
                    $this->getAuthManager()->add($rule);
                }
                $item->ruleName = $rule->name;
            } else {
                $item->ruleName = null;
            }

            if ($this->model->getIsNewRecord()) {
                $this->getAuthManager()->add($item);
            } else {
                $this->getAuthManager()->update($this->model->itemName, $item);
                $this->model->itemName = $item->name;
            }

            $this->model->item = $item;

            return $this->updateChildren();

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Updates Auth Item children
     *
     * @return bool
     */
    protected function updateChildren()
    {
        $children = $this->getAuthManager()->getChildren($this->model->item->name);
        $childrenNames = array_keys($children);

        if (is_array($this->model->children)) {

            // remove those not linked anymore
            foreach (array_diff($childrenNames, $this->model->children) as $item) {
                if (!$this->getAuthManager()->removeChild($this->model->item, $children[$item])) {
                    return false;
                }

            }
            // add new children
            foreach (array_diff($this->model->children, $childrenNames) as $item) {
                if (!$this->getAuthManager()->addChild($this->model->item, $this->getAuthManager()->getItem($item))) {
                    return false;
                }
            }
        } else {
            return $this->getAuthManager()->removeChildren($this->model->item);
        }

        return true;
    }
}
