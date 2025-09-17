<?php

/**
 * CMS 6 Polyfill for SilverStripe\ORM\Sortable
 * 
 * This class provides forward compatibility by making the CMS 6 namespace
 * available in CMS 5, allowing you to migrate your code early.
 * 
 * @package silverstripe-six-polyfill
 */

namespace SilverStripe\Model\List;

/**
 * Additional interface for {@link SS_List} classes that are sortable.
 *
 * All methods in this interface are immutable - they should return new instances with the sort
 * applied, rather than applying the sort in place
 *
 * @see SS_List
 * @see Filterable
 * @see Limitable
 *
 * @template T
 * @implements SS_List<T>
 * @deprecated 5.4.0 Will be merged into SS_List
 */
interface Sortable extends SS_List
{
    /**
     * Returns TRUE if the list can be sorted by a field.
     *
     * @param  string $by
     * @return bool
     */
    public function canSortBy($by);
    /**
     * Return a new instance of this list that is sorted by one or more fields. You can either pass in a single
     * field name and direction, or a map of field names to sort directions.
     *
     * @example $list = $list->sort('Name'); // default ASC sorting
     * @example $list = $list->sort('Name DESC'); // DESC sorting
     * @example $list = $list->sort('Name', 'ASC');
     * @example $list = $list->sort(array('Name'=>'ASC,'Age'=>'DESC'));
     *
     * @return static<T>
     */
    public function sort();
    /**
     * Return a new instance of this list based on reversing the current sort.
     *
     * @example $list = $list->reverse();
     *
     * @return static<T>
     */
    public function reverse();
}