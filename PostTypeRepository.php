<?php

namespace FFFlabel\Services;

class PostTypeRepository
{
    protected $machineName = 'post';

    public $repo;

    public function __construct()
    {
	    if (! isset( $this->machineName )) {
		    throw new \InvalidArgumentException(get_class( $this ) . ' must have a $machinename' );
	    }
    }

    public function createEntity($mixed)
    {
    	$calledClass = get_called_class();
    	$class = substr($calledClass, 0, strrpos($calledClass, '\\'));
    	return new $class($mixed);
    }

	/**
	 * @param $id
	 *
	 * @return mixed
	 */
	public static function find($id)
	{
		$self = new static();
		$self->repo = new PostRepository($self->machineName);

		if ($item = $self->repo->find($id)) {
			return $self->createEntity($item);
		}

		return false;
	}

	/**
	 * @param string $status - set value for the post_status, default - 'publish'
	 * @return array of objects
	 */
	public static function getAll($status = 'publish')
	{
		$self = new static();
		$self->repo = new PostRepository($self->machineName);

		$posts = [];

		$postsQuery = $self->repo->set('status', $status)->getAll();

		if (!empty($postsQuery)) {
			foreach ($postsQuery->posts as $post) {
				$posts[] = $self->createEntity($post);
			}
		}

		return $posts;
	}

	/**
	 * Find All post by the field
	 * @param        $field
	 * @param        $value
	 * @param string $compare
	 * @param string $defstatus
	 * @return array
	 */
	public static function findAllBy($field, $value, $compare = '=', $defstatus = 'publish')
	{
		$self = new static();
		$self->repo = new PostRepository($self->machineName);

		$posts = [];

		$postsQuery = $self->repo->set('status', $defstatus)->findAllBy($field, $value, $compare);

		if (!empty($postsQuery)) {
			foreach ($postsQuery->posts as $postitem) {
				$posts[] =  $self->createEntity($postitem);
			}
		}
		return $posts;
	}

	public static function findOneBy($field, $value, $compare = '=', $defstatus = 'publish')
	{
		$post = false;
		$self = new static();
		$self->repo = new PostRepository($self->machineName);

		$postitem = $self->repo->set('status', $defstatus)->findOneBy($field, $value, $compare);

		if (!empty($postitem)) {
			$post =  $self->createEntity($postitem);
		}

		return $post;
	}

	public static function findSmart(array $param, array $repo_param = [])
	{
		$post = false;
		$self = new static();
		$self->repo = new PostRepository($self->machineName, $repo_param);

		$postitem = $self->repo->findSmartOne($param);

		if ($postitem) {
			$post =  $self->createEntity($postitem);
		}
		return $post;
	}

	public static function findSmartAll(array $param, array $repo_param = [], $returnQuery = false)
	{
		$posts = [];
		$self = new static();
		$self->repo = new PostRepository($self->machineName, $repo_param);

		$postsQuery = $self->repo->findSmartAll($param);

		if ($returnQuery) {
		    return $postsQuery;
        }

		if (!empty($postsQuery)) {
			foreach ($postsQuery->posts as $postitem) {
				$posts[] =  is_int($postitem) ? $postitem :  $self->createEntity($postitem);
			}
		}
		return $posts;
	}

    /**
     * Get all terms from taxonomy
     * @param $taxonomy
     * @param array $args
     * @return array|int|\WP_Error
     */
    public static function getAllTerms($taxonomy, $output = 'ARRAY', $args = [])
    {

        $defaults = array(
            'taxonomy'=> $taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        );

        $args = wp_parse_args($args, $defaults);

        $terms = get_terms($args);

        if ($output === 'ARRAY') {
            $collection = [];
            if ( $terms ) {
                foreach ( $terms as $term_item ) {
                    $collection[ $term_item->term_id ] = $term_item->name;
                }
            }
            return $collection;
        }

        return $terms;
    }
}
