import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Flex,
	FlexBlock,
	Notice,
	Spinner,
} from '@wordpress/components';

export default function AnalyticsTab( { data, isLoading, onReload } ) {
	if ( isLoading && ! data ) {
		return createElement( Spinner );
	}

	const sample = data?.sample || {};
	const dailyChallenges = Array.isArray( data?.daily_challenges )
		? data.daily_challenges
		: [];
	const dailyVerify = Array.isArray( data?.daily_verify )
		? data.daily_verify
		: [];
	const topContexts = Array.isArray( data?.top_contexts )
		? data.top_contexts
		: [];
	const featureHits = Array.isArray( data?.feature_hits )
		? data.feature_hits
		: [];

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
		sample?.truncated
			? createElement(
					Notice,
					{ status: 'warning', isDismissible: false },
					__(
						'Analytics sample is truncated for performance. Refine filters for full fidelity.',
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
					__( 'Analytics', 'anti-spam-for-wordpress' )
				)
			),
			createElement(
				CardBody,
				null,
				createElement(
					'p',
					null,
					`${ __( 'Events analyzed', 'anti-spam-for-wordpress' ) }: ${
						sample.analyzed_events || 0
					} / ${ sample.total_events || 0 }`
				),
				createElement(
					'p',
					null,
					`${ __(
						'Rate-limit total',
						'anti-spam-for-wordpress'
					) }: ${ data?.cards?.rate_limit_total || 0 }`
				),
				createElement(
					Button,
					{ variant: 'secondary', onClick: () => onReload() },
					__( 'Refresh', 'anti-spam-for-wordpress' )
				)
			)
		),
		createElement(
			Card,
			null,
			createElement(
				CardHeader,
				null,
				createElement(
					'strong',
					null,
					__( 'Challenges Issued by Day', 'anti-spam-for-wordpress' )
				)
			),
			createElement(
				CardBody,
				null,
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
								__( 'Day', 'anti-spam-for-wordpress' )
							),
							createElement(
								'th',
								null,
								__( 'Count', 'anti-spam-for-wordpress' )
							)
						)
					),
					createElement(
						'tbody',
						null,
						dailyChallenges.map( ( row ) =>
							createElement(
								'tr',
								{ key: row.day },
								createElement( 'td', null, row.day ),
								createElement( 'td', null, row.count )
							)
						),
						dailyChallenges.length === 0
							? createElement(
									'tr',
									null,
									createElement(
										'td',
										{ colSpan: 2 },
										__(
											'No data',
											'anti-spam-for-wordpress'
										)
									)
								)
							: null
					)
				)
			)
		),
		createElement(
			Card,
			null,
			createElement(
				CardHeader,
				null,
				createElement(
					'strong',
					null,
					__( 'Verify Pass/Fail by Day', 'anti-spam-for-wordpress' )
				)
			),
			createElement(
				CardBody,
				null,
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
								__( 'Day', 'anti-spam-for-wordpress' )
							),
							createElement(
								'th',
								null,
								__( 'Pass', 'anti-spam-for-wordpress' )
							),
							createElement(
								'th',
								null,
								__( 'Fail', 'anti-spam-for-wordpress' )
							)
						)
					),
					createElement(
						'tbody',
						null,
						dailyVerify.map( ( row ) =>
							createElement(
								'tr',
								{ key: row.day },
								createElement( 'td', null, row.day ),
								createElement( 'td', null, row.pass ),
								createElement( 'td', null, row.fail )
							)
						),
						dailyVerify.length === 0
							? createElement(
									'tr',
									null,
									createElement(
										'td',
										{ colSpan: 3 },
										__(
											'No data',
											'anti-spam-for-wordpress'
										)
									)
								)
							: null
					)
				)
			)
		),
		createElement(
			Flex,
			{ gap: 4, wrap: true },
			createElement(
				FlexBlock,
				null,
				createElement(
					Card,
					null,
					createElement(
						CardHeader,
						null,
						createElement(
							'strong',
							null,
							__( 'Top Contexts', 'anti-spam-for-wordpress' )
						)
					),
					createElement(
						CardBody,
						null,
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
										__(
											'Context',
											'anti-spam-for-wordpress'
										)
									),
									createElement(
										'th',
										null,
										__( 'Count', 'anti-spam-for-wordpress' )
									)
								)
							),
							createElement(
								'tbody',
								null,
								topContexts.map( ( row ) =>
									createElement(
										'tr',
										{ key: row.label },
										createElement( 'td', null, row.label ),
										createElement( 'td', null, row.count )
									)
								),
								topContexts.length === 0
									? createElement(
											'tr',
											null,
											createElement(
												'td',
												{ colSpan: 2 },
												__(
													'No data',
													'anti-spam-for-wordpress'
												)
											)
										)
									: null
							)
						)
					)
				)
			),
			createElement(
				FlexBlock,
				null,
				createElement(
					Card,
					null,
					createElement(
						CardHeader,
						null,
						createElement(
							'strong',
							null,
							__(
								'Feature Hit Totals',
								'anti-spam-for-wordpress'
							)
						)
					),
					createElement(
						CardBody,
						null,
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
										__(
											'Feature',
											'anti-spam-for-wordpress'
										)
									),
									createElement(
										'th',
										null,
										__( 'Count', 'anti-spam-for-wordpress' )
									)
								)
							),
							createElement(
								'tbody',
								null,
								featureHits.map( ( row ) =>
									createElement(
										'tr',
										{ key: row.label },
										createElement( 'td', null, row.label ),
										createElement( 'td', null, row.count )
									)
								),
								featureHits.length === 0
									? createElement(
											'tr',
											null,
											createElement(
												'td',
												{ colSpan: 2 },
												__(
													'No data',
													'anti-spam-for-wordpress'
												)
											)
										)
									: null
							)
						)
					)
				)
			)
		)
	);
}
