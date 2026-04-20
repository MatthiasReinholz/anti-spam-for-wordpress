import {
	createElement,
	useCallback,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
	Flex,
	FlexBlock,
	Notice,
	Panel,
	PanelBody,
	SelectControl,
	Spinner,
	TabPanel,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import {
	fetchOperation,
	fetchPath,
	getAdminUiConfig,
	getOperationPath,
} from '../shared/api-client';

const TAB_SETTINGS = 'settings';
const TAB_EVENTS = 'events';
const TAB_ANALYTICS = 'analytics';
const TAB_OPTIONS = [ TAB_SETTINGS, TAB_EVENTS, TAB_ANALYTICS ];
const SETTINGS_READ_OPERATION = 'settings.read';
const SETTINGS_UPDATE_OPERATION = 'settings.update';
const EVENTS_LIST_OPERATION = 'events.list';
const ANALYTICS_READ_OPERATION = 'analytics.read';

function getInitialTab() {
	const params = new URLSearchParams( window.location.search || '' );
	const tab = ( params.get( 'tab' ) || TAB_SETTINGS ).toLowerCase();
	return TAB_OPTIONS.includes( tab ) ? tab : TAB_SETTINGS;
}

function setTabInUrl( tab ) {
	const params = new URLSearchParams( window.location.search || '' );
	params.set( 'tab', tab );
	const next = `${ window.location.pathname }?${ params.toString() }`;
	window.history.replaceState( {}, '', next );
}

function mapOptions( options ) {
	if ( ! Array.isArray( options ) ) {
		return [];
	}

	return options.map( ( option ) => ( {
		value: String( option?.value ?? '' ),
		label: String( option?.label ?? option?.value ?? '' ),
	} ) );
}

function buildSettingsDraft( payload ) {
	const values = {};
	const sections = Array.isArray( payload?.sections ) ? payload.sections : [];

	sections.forEach( ( section ) => {
		const fields = Array.isArray( section?.fields ) ? section.fields : [];
		fields.forEach( ( field ) => {
			if ( ! field?.option ) {
				return;
			}
			values[ field.option ] = field.value;
		} );
	} );

	return values;
}

function buildQuery( params ) {
	const search = new URLSearchParams();
	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value === undefined || value === null || value === '' ) {
			return;
		}
		search.set( key, String( value ) );
	} );

	return search.toString();
}

function SettingsField( { field, value, onChange, values } ) {
	const type = field.type || 'text';

	if (
		field.option === 'asfw_privacy_url' &&
		values?.asfw_privacy_page !== 'custom'
	) {
		return null;
	}

	const disabled = Boolean( field.disabled );

	if ( type === 'checkbox' ) {
		return createElement( CheckboxControl, {
			label: field.label,
			help: field.hint || field.description || '',
			checked: Boolean( value ),
			disabled,
			onChange,
		} );
	}

	if ( type === 'select' || type === 'privacy_target' ) {
		const options = mapOptions( field.options );
		return createElement( SelectControl, {
			label: field.label,
			help: field.hint || field.description || '',
			value: String( value ?? '' ),
			disabled,
			options,
			onChange,
		} );
	}

	if ( type === 'textarea' ) {
		return createElement( TextareaControl, {
			label: field.label,
			help: field.hint || field.description || '',
			value: String( value ?? '' ),
			disabled,
			rows: 4,
			onChange,
		} );
	}

	return createElement( TextControl, {
		label: field.label,
		help: field.hint || field.description || '',
		type:
			type === 'url' || type === 'number' || type === 'password'
				? type
				: 'text',
		value: String( value ?? '' ),
		disabled,
		onChange,
	} );
}

function SettingsTab( {
	payload,
	values,
	isLoading,
	isSaving,
	onChange,
	onSave,
} ) {
	if ( isLoading ) {
		return createElement( Spinner );
	}

	const sections = Array.isArray( payload?.sections ) ? payload.sections : [];
	const summaryRows = Array.isArray( payload?.summary?.rows )
		? payload.summary.rows
		: [];
	const contexts = Array.isArray( payload?.context_catalog )
		? payload.context_catalog
		: [];
	const killSwitch = payload?.summary?.kill_switch === 'active';

	return createElement(
		Flex,
		{ direction: 'column', gap: 4 },
		createElement(
			Card,
			null,
			createElement(
				CardHeader,
				null,
				createElement(
					'strong',
					null,
					__( 'Control Plane Summary', 'anti-spam-for-wordpress' )
				)
			),
			createElement(
				CardBody,
				null,
				createElement(
					'p',
					null,
					createElement(
						'strong',
						null,
						__( 'Kill switch:', 'anti-spam-for-wordpress' )
					),
					' ',
					killSwitch
						? __( 'Active', 'anti-spam-for-wordpress' )
						: __( 'Inactive', 'anti-spam-for-wordpress' )
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
								__( 'Feature', 'anti-spam-for-wordpress' )
							),
							createElement(
								'th',
								null,
								__( 'State', 'anti-spam-for-wordpress' )
							),
							createElement(
								'th',
								null,
								__( 'Mode', 'anti-spam-for-wordpress' )
							),
							createElement(
								'th',
								null,
								__(
									'Background work',
									'anti-spam-for-wordpress'
								)
							),
							createElement(
								'th',
								null,
								__( 'Experimental', 'anti-spam-for-wordpress' )
							)
						)
					),
					createElement(
						'tbody',
						null,
						summaryRows.map( ( row, index ) =>
							createElement(
								'tr',
								{ key: `${ row?.label || 'row' }-${ index }` },
								createElement(
									'td',
									null,
									String( row?.label || '' )
								),
								createElement(
									'td',
									null,
									String( row?.enabled || '' )
								),
								createElement(
									'td',
									null,
									createElement(
										'code',
										null,
										String( row?.mode || '' )
									)
								),
								createElement(
									'td',
									null,
									String( row?.background || '' )
								),
								createElement(
									'td',
									null,
									String( row?.experimental || '' )
								)
							)
						)
					)
				)
			)
		),
		createElement(
			'form',
			{
				onSubmit: ( event ) => {
					event.preventDefault();
					onSave();
				},
			},
			sections.map( ( section ) =>
				createElement(
					Card,
					{ key: section.id },
					createElement(
						CardHeader,
						null,
						createElement( 'strong', null, section.title )
					),
					createElement(
						CardBody,
						null,
						section.description
							? createElement( 'p', null, section.description )
							: null,
						createElement(
							Panel,
							null,
							createElement(
								PanelBody,
								{ opened: true },
								( Array.isArray( section.fields )
									? section.fields
									: []
								).map( ( field ) =>
									createElement( SettingsField, {
										key: field.id || field.option,
										field,
										value: values[ field.option ],
										values,
										onChange: ( next ) =>
											onChange( field.option, next ),
									} )
								)
							)
						)
					)
				)
			),
			createElement(
				Flex,
				{ justify: 'flex-start', gap: 3 },
				createElement(
					Button,
					{ variant: 'primary', type: 'submit', isBusy: isSaving },
					__( 'Save Settings', 'anti-spam-for-wordpress' )
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
					__( 'Context Catalog', 'anti-spam-for-wordpress' )
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
								__( 'Context', 'anti-spam-for-wordpress' )
							),
							createElement(
								'th',
								null,
								__( 'Group', 'anti-spam-for-wordpress' )
							),
							createElement(
								'th',
								null,
								__( 'Description', 'anti-spam-for-wordpress' )
							)
						)
					),
					createElement(
						'tbody',
						null,
						contexts.map( ( entry ) =>
							createElement(
								'tr',
								{ key: entry.context },
								createElement(
									'td',
									null,
									createElement( 'code', null, entry.context )
								),
								createElement(
									'td',
									null,
									entry.group_label || entry.group || ''
								),
								createElement(
									'td',
									null,
									entry.description || ''
								)
							)
						)
					)
				)
			)
		)
	);
}

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

function EventsTab( {
	data,
	isLoading,
	filtersDraft,
	onChangeFiltersDraft,
	onApplyFilters,
	onReload,
	onSetPage,
} ) {
	if ( isLoading ) {
		return createElement( Spinner );
	}

	const items = Array.isArray( data?.items ) ? data.items : [];
	const pagination = data?.pagination || {};
	const page = Number( pagination.page || 1 );
	const totalPages = Number( pagination.total_pages || 1 );

	return createElement(
		Flex,
		{ direction: 'column', gap: 4 },
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
							{ variant: 'secondary', onClick: onReload },
							__( 'Refresh', 'anti-spam-for-wordpress' )
						)
					)
				)
			)
		)
	);
}

function AnalyticsTab( { data, isLoading, onReload } ) {
	if ( isLoading ) {
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
		{ direction: 'column', gap: 4 },
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
					{ variant: 'secondary', onClick: onReload },
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

export default function App() {
	const adminUiConfig = getAdminUiConfig();
	const [ activeTab, setActiveTab ] = useState( getInitialTab() );
	const [ notice, setNotice ] = useState( null );

	const [ settingsPayload, setSettingsPayload ] = useState( null );
	const [ settingsValues, setSettingsValues ] = useState( {} );
	const [ settingsLoading, setSettingsLoading ] = useState( false );
	const [ settingsSaving, setSettingsSaving ] = useState( false );

	const [ eventsData, setEventsData ] = useState( null );
	const [ eventsLoading, setEventsLoading ] = useState( false );
	const [ eventsFiltersDraft, setEventsFiltersDraft ] = useState( {
		date_from: '',
		date_to: '',
		context: '',
		type: '',
		feature: '',
		decision: '',
	} );
	const [ eventsFiltersApplied, setEventsFiltersApplied ] = useState( {
		date_from: '',
		date_to: '',
		context: '',
		type: '',
		feature: '',
		decision: '',
	} );
	const [ eventsPage, setEventsPage ] = useState( 1 );

	const [ analyticsData, setAnalyticsData ] = useState( null );
	const [ analyticsLoading, setAnalyticsLoading ] = useState( false );

	const tabs = useMemo(
		() => [
			{
				name: TAB_SETTINGS,
				title: __( 'Settings', 'anti-spam-for-wordpress' ),
			},
			{
				name: TAB_EVENTS,
				title: __( 'Events', 'anti-spam-for-wordpress' ),
			},
			{
				name: TAB_ANALYTICS,
				title: __( 'Analytics', 'anti-spam-for-wordpress' ),
			},
		],
		[]
	);

	const loadSettings = useCallback( async () => {
		setSettingsLoading( true );
		try {
			const payload = await fetchOperation( SETTINGS_READ_OPERATION );
			setSettingsPayload( payload || null );
			setSettingsValues( buildSettingsDraft( payload || {} ) );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message:
					error?.message ||
					__( 'Failed to load settings.', 'anti-spam-for-wordpress' ),
			} );
		} finally {
			setSettingsLoading( false );
		}
	}, [] );

	const saveSettings = useCallback( async () => {
		setSettingsSaving( true );
		try {
			const response = await fetchOperation( SETTINGS_UPDATE_OPERATION, {
				method: 'POST',
				data: { values: settingsValues },
			} );
			const nextPayload = response?.settings || null;
			setSettingsPayload( nextPayload );
			setSettingsValues( buildSettingsDraft( nextPayload || {} ) );
			setNotice( {
				status: 'success',
				message: __( 'Settings saved.', 'anti-spam-for-wordpress' ),
			} );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message:
					error?.message ||
					__( 'Failed to save settings.', 'anti-spam-for-wordpress' ),
			} );
		} finally {
			setSettingsSaving( false );
		}
	}, [ settingsValues ] );

	const loadEvents = useCallback(
		async ( nextFilters = eventsFiltersApplied, nextPage = eventsPage ) => {
			setEventsLoading( true );
			try {
				const path = `${ getOperationPath(
					EVENTS_LIST_OPERATION
				) }?${ buildQuery( {
					...nextFilters,
					page_number: nextPage,
					per_page: 50,
				} ) }`;
				const payload = await fetchPath( path );
				setEventsData( payload || null );
			} catch ( error ) {
				setNotice( {
					status: 'error',
					message:
						error?.message ||
						__(
							'Failed to load events.',
							'anti-spam-for-wordpress'
						),
				} );
			} finally {
				setEventsLoading( false );
			}
		},
		[ eventsFiltersApplied, eventsPage ]
	);

	const loadAnalytics = useCallback(
		async ( nextFilters = eventsFiltersApplied ) => {
			setAnalyticsLoading( true );
			try {
				const path = `${ getOperationPath(
					ANALYTICS_READ_OPERATION
				) }?${ buildQuery( nextFilters ) }`;
				const payload = await fetchPath( path );
				setAnalyticsData( payload || null );
			} catch ( error ) {
				setNotice( {
					status: 'error',
					message:
						error?.message ||
						__(
							'Failed to load analytics.',
							'anti-spam-for-wordpress'
						),
				} );
			} finally {
				setAnalyticsLoading( false );
			}
		},
		[ eventsFiltersApplied ]
	);

	useEffect( () => {
		setTabInUrl( activeTab );

		if (
			activeTab === TAB_SETTINGS &&
			! settingsPayload &&
			! settingsLoading
		) {
			loadSettings();
		}

		if ( activeTab === TAB_EVENTS && ! eventsData && ! eventsLoading ) {
			loadEvents();
		}

		if (
			activeTab === TAB_ANALYTICS &&
			! analyticsData &&
			! analyticsLoading
		) {
			loadAnalytics();
		}
	}, [
		activeTab,
		analyticsData,
		analyticsLoading,
		eventsData,
		eventsLoading,
		loadAnalytics,
		loadEvents,
		loadSettings,
		settingsLoading,
		settingsPayload,
	] );

	useEffect( () => {
		if ( activeTab !== TAB_EVENTS ) {
			return;
		}

		loadEvents();
	}, [ activeTab, eventsPage, loadEvents ] );

	return createElement(
		Flex,
		{ direction: 'column', gap: 4 },
		createElement(
			FlexBlock,
			null,
			createElement(
				'h1',
				null,
				adminUiConfig.pluginName || 'Anti Spam for WordPress'
			)
		),
		notice
			? createElement(
					Notice,
					{
						status: notice.status || 'info',
						onRemove: () => setNotice( null ),
						isDismissible: true,
					},
					notice.message
			  )
			: null,
		createElement( TabPanel, {
			className: 'asfw-admin-tab-panel',
			activeClass: 'is-active',
			tabs,
			initialTabName: activeTab,
			onSelect: ( tabName ) => {
				setActiveTab( tabName );
			},
			children: ( tab ) => {
				if ( tab.name === TAB_SETTINGS ) {
					return createElement( SettingsTab, {
						payload: settingsPayload,
						values: settingsValues,
						isLoading: settingsLoading,
						isSaving: settingsSaving,
						onChange: ( option, value ) =>
							setSettingsValues( ( prev ) => ( {
								...prev,
								[ option ]: value,
							} ) ),
						onSave: saveSettings,
					} );
				}

				if ( tab.name === TAB_EVENTS ) {
					return createElement( EventsTab, {
						data: eventsData,
						isLoading: eventsLoading,
						filtersDraft: eventsFiltersDraft,
						onChangeFiltersDraft: setEventsFiltersDraft,
						onApplyFilters: () => {
							setEventsFiltersApplied( eventsFiltersDraft );
							setEventsPage( 1 );
							loadEvents( eventsFiltersDraft, 1 );
							setAnalyticsData( null );
							if ( activeTab === TAB_ANALYTICS ) {
								loadAnalytics( eventsFiltersDraft );
							}
						},
						onReload: loadEvents,
						onSetPage: setEventsPage,
					} );
				}

				return createElement( AnalyticsTab, {
					data: analyticsData,
					isLoading: analyticsLoading,
					onReload: loadAnalytics,
				} );
			},
		} )
	);
}
