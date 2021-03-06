<?php
class OrderedProduct extends AppModel {
    var $name = 'OrderedProduct';
    //The Associations below have been created with all possible keys, those that are not needed can be removed

    var $belongsTo = array(
        'User' => array(
                'className' => 'User',
                'foreignKey' => 'user_id',
                'conditions' => '',
                'fields' => '',
                'order' => ''
        ),
        'Seller' => array(
                'className' => 'Seller',
                'foreignKey' => 'seller_id',
                'conditions' => '',
                'fields' => '',
                'order' => ''
        ),
        'Product' => array(
                'className' => 'Product',
                'foreignKey' => 'product_id',
                'conditions' => '',
                'fields' => '',
                'order' => 'product_category_id'
        ),
        'Hamper' => array(
                'className' => 'Hamper',
                'foreignKey' => 'hamper_id',
                'conditions' => '',
                'fields' => '',
                'order' => ''
        ),
    );

    var $hasMany = array(
        'MoneyBox' => array(
                'className' => 'MoneyBox',
                'foreignKey' => 'ordered_product_id',
                'dependent' => false,
                'conditions' => '',
                'fields' => '',
                'order' => '',
                'limit' => '',
                'offset' => '',
                'exclusive' => '',
                'finderQuery' => '',
                'counterQuery' => ''
        )
    );

    var $actsAs = array('Containable');

    function save($data = null, $validate = true, $fieldList = array()) {
        // se esiste un altro ordine uguale, sommo all'ordine precedente
        $existing = false;
        if(isset(
        $data['OrderedProduct']['user_id'],
        $data['OrderedProduct']['seller_id'],
        $data['OrderedProduct']['product_id'],
        $data['OrderedProduct']['hamper_id']
        )) {
			if(!isset($data['OrderedProduct']['option_1'])) {
				$data['OrderedProduct']['option_1'] =  null;
			}
			if(!isset($data['OrderedProduct']['option_2'])) {
				$data['OrderedProduct']['option_2'] =  null;
			}
			if(!isset($data['OrderedProduct']['note'])) {
				$data['OrderedProduct']['note'] =  null;
			}

            $existing = $this->find('first', array('conditions' => array(
                    'OrderedProduct.user_id' => $data['OrderedProduct']['user_id'],
                    'OrderedProduct.seller_id' => $data['OrderedProduct']['seller_id'],
                    'OrderedProduct.product_id' => $data['OrderedProduct']['product_id'],
                    'OrderedProduct.hamper_id' => $data['OrderedProduct']['hamper_id'],
                    'OrderedProduct.option_1' => $data['OrderedProduct']['option_1'],
                    'OrderedProduct.option_2' => $data['OrderedProduct']['option_2'],
                    'OrderedProduct.note' => $data['OrderedProduct']['note']
                ),
                'contain' => array()));
        }

        if(!empty($existing)) {
            $data['OrderedProduct']['id'] = $existing['OrderedProduct']['id'];
            $data['OrderedProduct']['quantity'] = $data['OrderedProduct']['quantity'] + $existing['OrderedProduct']['quantity'];
            $data['OrderedProduct']['value'] = str_replace(',', '.', $data['OrderedProduct']['value'] + $existing['OrderedProduct']['value']);
        }

        return parent::save($data, $validate, $fieldList);
    }

	function beforeFind($queryData) {
		if (Configure::read('ReferentUser.allowed_sellers')) {
			$allowed_sellers=Configure::read('ReferentUser.allowed_sellers');
			$forbidden_sellers=0;

			if (isset($queryData['conditions']['OrderedProduct.seller_id'])) {				
				$testCondition = $queryData['conditions']['OrderedProduct.seller_id'];			
				// devo vedere se e' un valore singolo e cercarlo
				// altrimenti se e' un array devo scorrerlo e cercarli tutti
				if (is_array($testCondition))
				{
					$arrayLen = count($testCondition);
					for($x=0;$x<$arrayLen;$x++) {
						if (!in_array($testCondition[$x],$allowed_sellers))
  						 { $forbidden_sellers += 1; }
 					}	
				}
				else				
					if (!in_array($testCondition,$allowed_sellers))
					{
						$forbidden_sellers=1;
					}
				if ($forbidden_sellers <> 0)
					// Situazione anomala, devo invalidare   						
					$queryData['conditions']['OrderedProduct.seller_id'] =  FALSE;								
			}
			else
			{
				// Aggiungo i fornitori di cui l'utente e' referente
				$queryData = array_merge_recursive($queryData,
						array('conditions' => array('OrderedProduct.seller_id' => Configure::read('ReferentUser.allowed_sellers'))));
			}
			
			
		}
		return $queryData;
	}
	

    function buildOrder($data, $user) {

        $this->Product->recursive = -1;
        $product = $this->Product->read(null, $data['OrderedProduct']['product_id']);

        if(!$this->Hamper->isActive($data['OrderedProduct']['hamper_id'])) {
            return false;
        }

        if($data['OrderedProduct']['seller_id'] != $product['Product']['seller_id']) {
            return false;
        }

        //imposto il modello
        $data['OrderedProduct']['user_id'] = $user['User']['id'];

        $data['OrderedProduct']['value'] = str_replace(',', '.', ($product['Product']['value'] * $data['OrderedProduct']['quantity']));

        $data['OrderedProduct']['paid'] = 0;
        $data['OrderedProduct']['retired'] = 0;

        return $data;
    }

    function getUserOrder($user) {
        $userOrder = $this->find('all', array(
            'conditions' => array('retired' => '0', 'OrderedProduct.user_id' => $user['User']['id']),
            'order' => 'Hamper.delivery_date_on',
            'contain' => array(
                'Product',
                'Seller'=> array('fields' => array('name')),
                'Hamper' => array('fields' => array('name', 'start_date', 'end_date', 'checkout_date', 'delivery_date_on', 'delivery_date_off', 'delivery_position')))
        ));
        return $userOrder;
    }

    function getPendingUsers($full = false) {
        $orderedProducts = $this->getPending(); 

        $user_ids = Set::extract('/OrderedProduct/user_id', $orderedProducts); 
        
        $_users = $this->User->find('all', array(
            'conditions' => array('User.id' => $user_ids),
            'order' => array('User.last_name asc', 'User.first_name asc'),
            'contain' => array()
        ));

        if($full) {
            return $_users;
        }

        $users = Set::combine($_users, '{n}.User.id', '{n}.User.fullname');

        return $users;
    }

	function getPendingHampers($full = false) {
		$pendingProducts = $this->getPending();
		$pendingHampersIds = array_unique(Set::extract('/OrderedProduct/hamper_id', $pendingProducts));

		$_pendingHampers = $this->Hamper->find('all', array(
			'conditions' => array('Hamper.id' => $pendingHampersIds),
			'contain' => array('Seller.name')
		));

		if($full) {
			return $_pendingHampers;
		}

		$pendingHampers = array();
		foreach($_pendingHampers as $hamper) {
			$pendingHampers[$hamper['Hamper']['id']] = $hamper['Hamper']['name']
				. ' ' . __('di', true) . ' ' . $hamper['Seller']['name']
				. ' ' . __('di', true) . ' ' . digi_date($hamper['Hamper']['delivery_date_on']);
		}
		return $pendingHampers;
	}
    
    function getPendingSellers($full = false) {
        $orderedProducts = $this->getPending();

        $seller_ids = Set::extract('/OrderedProduct/seller_id', $orderedProducts);

        $_sellers = $this->Seller->find('all', array(
            'conditions' => array('Seller.id' => $seller_ids),
            'order' => 'Seller.name asc',
            'contain' => array()
        ));

        if($full) {
            return $_sellers;
        }

        $sellers = Set::combine($_sellers, '{n}.Seller.id', '{n}.Seller.name');

        return $sellers;
    }

    function getPending() {
        if(!isset($this->pendingProducts)) {
            $this->pendingProducts = $this->find('all', array(
                'conditions' => array(
                    'or' => array(
                        'paid' => 0,
                        'retired' => 0)),
                'fields' => array('id', 'user_id', 'seller_id', 'hamper_id'),
				'recursive' => -1
            ));
        }
        return $this->pendingProducts;
    }

	//restiruisce i prodotti pendenti per utente
    function getPendingForUser($user_id) {
        $pendings = $this->find('all', array(
            'conditions' => array('OrderedProduct.user_id' => $user_id, 'or' => array('paid' => 0, 'retired' => 0)),
            'contain' => array(
                'User' => array('fields' => array('id', 'fullname')),
                'Seller' => array('fields' => array('id', 'name')),
                'Product' => array('fields' => array('id', 'name', 'code', 'option_1', 'option_2', 'units')),
                'Hamper' => array('fields' => array('id', 'delivery_date_on')))
        ));
        return $pendings;
    }

    function verify($id, $user) {
        $orderedProduct = $this->findById($id);

        if(empty($orderedProduct)) {
            return false;
        }

        if(!$this->Hamper->isActive($orderedProduct['OrderedProduct']['hamper_id'])) {
            return false;
        }

        if($orderedProduct['OrderedProduct']['user_id'] != $user['User']['id']) {
            return false;
        }

        return true;
    }

    function massUpdate($field, $hamper_id) {
        $ordersToUpdate = $this->find('all', array(
            'conditions' => array('OrderedProduct.hamper_id' => $hamper_id),
            'contain' => array(
                'Product.name',
                'Seller.name'
            )
        ));
        $orders_id = Set::extract('/OrderedProduct/id', $ordersToUpdate);
        $return = $this->updateAll(array('OrderedProduct.'.$field => 1, 'OrderedProduct.modified' => 'NOW()'), array('OrderedProduct.id' => $orders_id));

        //se il campo da modificare è "paid", aggiorno anche moneyboxes
        if($field == 'paid') { 
            foreach($ordersToUpdate as $data) {
                $value = $data['OrderedProduct']['value'];
                $user_id = $data['OrderedProduct']['user_id'];
                $ordered_product_id = $data['OrderedProduct']['id'];
                $message = 'Pagamento di '.$data['OrderedProduct']['quantity'].' '.$data['Product']['name'].' verso '.$data['Seller']['name'];
                if(!$this->updateMoneyBox('out', $value, $user_id, $ordered_product_id, $message)) {
                    $return = false;
                }
            }
        }
        return $return;
    }

    function setPaid($id) {
        $data = $this->find('first', array(
            'conditions' => array('OrderedProduct.id' => $id),
            'contain' => array(
                'Product.name',
                'Seller.name'
            )));
        if($this->saveField('paid', 1)) {
            $value = $data['OrderedProduct']['value'];
            $user_id = $data['OrderedProduct']['user_id'];
            $ordered_product_id = $data['OrderedProduct']['id'];
            $message = 'Pagamento di '.$data['OrderedProduct']['quantity'].' '.$data['Product']['name'].' verso '.$data['Seller']['name'];
            return $this->updateMoneyBox('out', $value, $user_id, $ordered_product_id, $message);
        } else {
            return false;
        }
    }
    function setNotPaid($id) {
        $data = $this->find('first', array(
            'conditions' => array('OrderedProduct.id' => $id),
            'contain' => array(
                'Product.name',
                'Seller.name'
            )));
        if($this->saveField('paid', 0)) {
            $value = $data['OrderedProduct']['value'];
            $user_id = $data['OrderedProduct']['user_id'];
            $ordered_product_id = $data['OrderedProduct']['id'];
            $message = 'Restituzione pagamento per '.$data['OrderedProduct']['quantity'].' '.$data['Product']['name'].' da '.$data['Seller']['name'];
            return $this->updateMoneyBox('in', $value, $user_id, $ordered_product_id, $message);
        } else {
            return false;
        }
    }
    function setRetired($id) {
        $this->recursive = -1;
        $data = $this->findById($id);
        if($this->saveField('retired', 1)) {
            return true;
        } else {
            return false;
        }
    }
    function setNotRetired($id) {
        $this->recursive = -1;
        $data = $this->findById($id);
        if($this->saveField('retired', 0)) {
            return true;
        } else {
            return false;
        }
    }

    function updateMoneyBox($direction, $value, $user_id, $ordered_product_id, $message = 'null') {
        $data = array('MoneyBox' => array(
            'OrderedProduct.user_id' => $user_id,
            'ordered_product_id' => $ordered_product_id,
            'text' => $message
        ));

        switch($direction) {
            case 'in':
                $data['MoneyBox']['value_in'] = $value;
            break;
            case 'out':
                $data['MoneyBox']['value_out'] = $value;
            break;
        }

        $this->MoneyBox->create();
        return $this->MoneyBox->save($data);
    }
}