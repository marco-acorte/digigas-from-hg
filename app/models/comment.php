<?php
class Comment extends AppModel {
	var $name = 'Comment';

	var $belongsTo = array('User',
                                'LastUser' => array(
                                        'className' => 'User',
                                        'foreignKey' => 'last_comment_user_id',
                                        'dependent' => true

				));
	var $hasMany = array(
		'RelatedComment' => array(
			'className' => 'Comment',
			'foreignKey' => 'parent_id',
			'conditions' => array('RelatedComment.active' => 1),
			'dependent' => true
		)
	);

	var $actsAs = array('Containable', 'Commentable');

	function toggle_active($id)
	{
		$value = $this->field('active', array('id'=>$id));
		switch($value)
		{
			case 1:
				$value = 0;
				break;
			case 0:
				$value = 1;
				break;
		}
		$this->create(array('id'=>$id));
		$this->saveField('active', $value);
	}
}