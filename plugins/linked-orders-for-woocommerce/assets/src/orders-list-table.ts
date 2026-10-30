// WooCommerce prints its order actions as plain links, so the views of a customer's or a tree's orders open in a new tab this way.
document
	.querySelectorAll< HTMLAnchorElement >(
		'a.view-all-customer-orders, a.view-all-linked-orders'
	)
	.forEach( ( link ) => {
		link.target = '_blank';
	} );
