import { createElement, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Flex,
	FlexBlock,
	Notice,
	TabPanel,
} from '@wordpress/components';
import { getAdminUiConfig } from '../shared/api-client';
import { buildQuery } from './api-operations';
import useAdminResource from './use-admin-resource';
import useSettings from './use-settings';
import SettingsTab from './settings-tab';
import EventsTab from './events-tab';
import AnalyticsTab from './analytics-tab';

const tabs = [
	{ name: 'settings', title: __( 'Settings', 'anti-spam-for-wordpress' ) },
	{ name: 'events', title: __( 'Events', 'anti-spam-for-wordpress' ) },
	{ name: 'analytics', title: __( 'Analytics', 'anti-spam-for-wordpress' ) },
];

function getInitialTab() {
	const tab = new URLSearchParams( window.location.search ).get( 'tab' );
	return tabs.some( ( item ) => item.name === tab ) ? tab : 'settings';
}

function ResourceView( { resource, children } ) {
	return createElement(
		Flex,
		{ direction: 'column', gap: 3 },
		resource.error
			? createElement(
					Notice,
					{ status: 'error', isDismissible: false },
					resource.error.message ||
						__(
							'Unable to load this page.',
							'anti-spam-for-wordpress'
						),
					createElement(
						Button,
						{
							variant: 'secondary',
							onClick: () => resource.reload(),
						},
						__( 'Try again', 'anti-spam-for-wordpress' )
					)
				)
			: null,
		resource.error && ! resource.data ? null : children
	);
}

export default function App() {
	const [ activeTab, setActiveTab ] = useState( getInitialTab );
	const [ notice, setNotice ] = useState( null );
	const [ filtersDraft, setFiltersDraft ] = useState( {} );
	const [ eventsQuery, setEventsQuery ] = useState( {
		filters: {},
		page: 1,
	} );
	const settings = useSettings( activeTab === 'settings', setNotice );
	const events = useAdminResource(
		'events.list',
		buildQuery( {
			...eventsQuery.filters,
			page_number: eventsQuery.page,
			per_page: 50,
		} ),
		activeTab === 'events'
	);
	const analytics = useAdminResource(
		'analytics.read',
		buildQuery( eventsQuery.filters ),
		activeTab === 'analytics'
	);

	useEffect( () => {
		const params = new URLSearchParams( window.location.search );
		params.set( 'tab', activeTab );
		window.history.replaceState(
			{},
			'',
			`${ window.location.pathname }?${ params }`
		);
	}, [ activeTab ] );

	return createElement(
		Flex,
		{ direction: 'column', gap: 4 },
		createElement(
			FlexBlock,
			null,
			createElement(
				'h1',
				null,
				getAdminUiConfig().pluginName || 'Anti Spam for WordPress'
			)
		),
		notice
			? createElement(
					Notice,
					{
						status: notice.status,
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
			onSelect: setActiveTab,
			children: ( tab ) => {
				if ( tab.name === 'settings' ) {
					return createElement(
						ResourceView,
						{ resource: settings },
						createElement( SettingsTab, {
							...settings,
							isLoading: ! settings.payload && ! settings.error,
						} )
					);
				}
				if ( tab.name === 'events' ) {
					return createElement(
						ResourceView,
						{ resource: events },
						createElement( EventsTab, {
							data: events.data,
							isLoading: events.isLoading,
							filtersDraft,
							onChangeFiltersDraft: setFiltersDraft,
							onApplyFilters: () =>
								setEventsQuery( {
									filters: { ...filtersDraft },
									page: 1,
								} ),
							onSetPage: ( page ) =>
								setEventsQuery( ( current ) => ( {
									...current,
									page,
								} ) ),
							onReload: events.reload,
						} )
					);
				}
				return createElement(
					ResourceView,
					{ resource: analytics },
					createElement( AnalyticsTab, {
						data: analytics.data,
						isLoading: analytics.isLoading,
						onReload: analytics.reload,
					} )
				);
			},
		} )
	);
}
