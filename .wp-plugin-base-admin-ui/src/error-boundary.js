import { Button, Notice } from '@wordpress/components';
import { Component, createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

function ErrorFallback( { error } ) {
	const message =
		error && typeof error.message === 'string' && error.message
			? error.message
			: __(
					'The admin UI encountered an unexpected error.',
					'anti-spam-for-wordpress'
			  );

	return createElement(
		'div',
		{ style: { padding: '24px 0' } },
		createElement(
			Notice,
			{ status: 'error', isDismissible: false },
			createElement(
				'p',
				null,
				__(
					'The admin UI could not finish rendering.',
					'anti-spam-for-wordpress'
				)
			),
			createElement( 'p', null, message ),
			createElement(
				Button,
				{
					variant: 'secondary',
					onClick: () => window.location.reload(),
				},
				__( 'Reload page', 'anti-spam-for-wordpress' )
			)
		)
	);
}

export default class ErrorBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { error: null };
	}

	static getDerivedStateFromError( error ) {
		return { error };
	}

	render() {
		if ( this.state.error ) {
			return createElement( ErrorFallback, { error: this.state.error } );
		}

		return this.props.children;
	}
}
