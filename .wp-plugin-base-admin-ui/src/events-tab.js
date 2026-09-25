import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Flex,
	Notice,
	Spinner,
	TextControl,
} from '@wordpress/components';

function EventsFilters( { filters, onChange, onApply } ) {
	const change = ( name, value ) =>
		onChange( { ...filters, [ name ]: value } );

	return createElement(
		Flex,
		{ align: 'flex-end', gap: 3, wrap: true },
		createElement( TextControl, {
			label: __( 'Date from', 'anti-spam-for-wordpress' ),
			type: 'date',
			value: filters.date_from || '',
			onChange: ( value ) => change( 'date_from', value ),
		} ),
		createElement( TextControl, {
			label: __( 'Date to', 'anti-spam-for-wordpress' ),
			type: 'date',
			value: filters.date_to || '',
			onChange: ( value ) => change( 'date_to', value ),
		} ),
		createElement( TextControl, {
			label: __( 'Context', 'anti-spam-for-wordpress' ),
			value: filters.context || '',
			onChange: ( value ) => change( 'context', value ),
		} ),
		createElement( TextControl, {
			label: __( 'Event type', 'anti-spam-for-wordpress' ),
			value: filters.type || '',
			onChange: ( value ) => change( 'type', value ),
		} ),
		createElement( TextControl, {
			label: __( 'Feature', 'anti-spam-for-wordpress' ),
			value: filters.feature || '',
			onChange: ( value ) => change( 'feature', value ),
		} ),
		createElement( TextControl, {
			label: __( 'Decision', 'anti-spam-for-wordpress' ),
			value: filters.decision || '',
			onChange: ( value ) => change( 'decision', value ),
		} ),
		createElement(
			Button,
			{ variant: 'primary', onClick: onApply },
			__( 'Apply filters', 'anti-spam-for-wordpress' )
		)
	);
}

export default function EventsTab( {
	data,
	isLoading,
	filtersDraft,
	onChangeFiltersDraft,
	onApplyFilters,
	onReload,
	onSetPage,
} ) {
	if ( isLoading && ! data ) {
		return createElement( Spinner );
	}

	const items = Array.isArray( data?.items ) ? data.items : [];
	const pagination = data?.pagination || {};
	const page = Number( pagination.page || 1 );
	const totalPages = Number( pagination.total_pages || 1 );

	return createElement(
		Flex,
		{ direction: 'column', gap: 4, 'aria-busy': isLoading },
		isLoading ? createElement( Spinner ) : null,
		! data?.logging_enabled
			? createElement(
					Notice,
					{ status: 'warning', isDismissible: false },
					__(
						'Event logging is currently disabled.',
						'anti-spam-for-wordpress'
					)
			  )
			: null,
		createElement(
			Card,
			null,
			createElement(
				CardHeader,
				null,
				createElement(
					'strong',
					null,
					__( 'Events', 'anti-spam-for-wordpress' )
				)
			),
			createElement(
				CardBody,
				null,
				createElement( EventsFilters, {
					filters: filtersDraft,
					onChange: onChangeFiltersDraft,
					onApply: onApplyFilters,
				} ),
				createElement(
					'p',
					{ className: 'asfw-admin-ui-muted' },
					`${ __(
						'Retention window',
						'anti-spam-for-wordpress'
					) }: ${ data?.retention_days || 0 } ${ __(
						'days',
						'anti-spam-for-wordpress'
					) }. `,
					data?.last_maintenance_run_utc
						? `${ __(
								'Last maintenance run',
								'anti-spam-for-wordpress'
						  ) }: ${ data.last_maintenance_run_utc } UTC.`
						: __(
								'Last maintenance run: not recorded yet.',
								'anti-spam-for-wordpress'
						  )
				),
				createElement(
					'table',
					{ className: 'widefat striped' },
					createElement(
						'thead',
						null,
						createElement(
							'tr',
							null,
							createElement(
								'th',
								null,
								__( 'Time', 'anti-spam-for-wordpress' )
							),
							createElement(
								'th',
								null,
								__( 'Type', 'anti-spam-for-wordpress' )
							),
							createElement(
								'th',
								null,
								__( 'Decision', 'anti-spam-for-wordpress' )
							),
							createElement(
								'th',
								null,
								__( 'Context', 'anti-spam-for-wordpress' )
							),
							createElement(
								'th',
								null,
								__( 'Feature', 'anti-spam-for-wordpress' )
							),
							createElement(
								'th',
								null,
								__( 'Details', 'anti-spam-for-wordpress' )
							)
						)
					),
					createElement(
						'tbody',
						null,
						items.length === 0
							? createElement(
									'tr',
									null,
									createElement(
										'td',
										{ colSpan: 6 },
										__(
											'No events found for the selected filters.',
											'anti-spam-for-wordpress'
										)
									)
							  )
							: items.map( ( item ) =>
									createElement(
										'tr',
										{
											key:
												item.id ||
												`${ item.created_at }-${ item.event_type }`,
										},
										createElement(
											'td',
											null,
											item.created_at || ''
										),
										createElement(
											'td',
											null,
											item.event_type || ''
										),
										createElement(
											'td',
											null,
											item.decision || ''
										),
										createElement(
											'td',
											null,
											item.context || ''
										),
										createElement(
											'td',
											null,
											item.feature || ''
										),
										createElement(
											'td',
											null,
											createElement(
												'code',
												null,
												item.details || '{}'
											)
										)
									)
							  )
					)
				),
				createElement(
					Flex,
					{ justify: 'space-between', align: 'center' },
					createElement(
						'span',
						null,
						`${ __(
							'Page',
							'anti-spam-for-wordpress'
						) } ${ page } ${ __(
							'of',
							'anti-spam-for-wordpress'
						) } ${ totalPages }`
					),
					createElement(
						Flex,
						{ gap: 2 },
						createElement(
							Button,
							{
								variant: 'secondary',
								disabled: page <= 1,
								onClick: () => onSetPage( page - 1 ),
							},
							__( 'Previous', 'anti-spam-for-wordpress' )
						),
						createElement(
							Button,
							{
								variant: 'secondary',
								disabled: page >= totalPages,
								onClick: () => onSetPage( page + 1 ),
							},
							__( 'Next', 'anti-spam-for-wordpress' )
						),
						createElement(
							Button,
							{ variant: 'secondary', onClick: () => onReload() },
							__( 'Refresh', 'anti-spam-for-wordpress' )
						)
					)
				)
			)
		)
	);
}
