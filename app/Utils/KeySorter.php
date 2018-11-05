<?php
namespace App\Utils;

/**
 * 支持对关联数组按指定key进行排序
 */

final class KeySorter
{
    const ORDER_ASC = 'asc';
    const ORDER_DESC = 'desc';

    protected $orderBy;
    protected $order;

    public function __construct($orderBy, $order)
    {
        $this->orderBy = $orderBy;
        $this->order = strtolower($order);
    }

    public function sort(&$arr)
    {
        if (!is_array($arr) || empty($arr)) {
            return true;
        }
        return uasort($arr, array($this, '_selfCmp'));
    }

    private function _selfCmp($lhs, $rhs)
    {
        $lhsField = NULL;
        $rhsField = NULL;
        if (is_array($lhs) && isset($lhs[$this->orderBy])) {
            $lhsField = $lhs[$this->orderBy];
        } else if (is_object($lhs) && isset($lhs->{$this->orderBy})) {
            $lhsField = $lhs->{$this->orderBy};
        }

        if (is_array($rhs) && isset($rhs[$this->orderBy])) {
            $rhsField = $rhs[$this->orderBy];
        } else if (is_object($rhs) && isset($rhs->{$this->orderBy})) {
            $rhsField = $rhs->{$this->orderBy};
        }

        if ($lhsField === NULL) {
            if ($this->order === self::ORDER_ASC) {
                return -1;
            } else {
                return 1;
            }
        }
        if ($rhsField === NULL) {
            if ($this->order === self::ORDER_ASC) {
                return 1;
            } else {
                return -1;
            }
        }

        if ($lhsField === $rhsField) {
            return 0;
        }

        if ($this->order === self::ORDER_ASC) {
            return ($lhsField < $rhsField) ? -1 : 1;
        } else {
            return ($lhsField > $rhsField) ? -1 : 1;
        }


    }

}