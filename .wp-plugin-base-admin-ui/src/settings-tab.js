import { createElement, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
	Flex,
	Panel,
	PanelBody,
	SelectControl,
	Spinner,
	TextareaControl,
	TextControl,
} from '@wordpress/components';

const PRIVACY_LEGAL_BASIS_OPTION = 'asfw_privacy_legal_basis';

function mapOptions( options ) {
	if ( ! Array.isArray( options ) ) {
		return [];
	}

	return options.map( ( option ) => ( {
		value: String( option?.value ?? '' ),
		label: String( option?.label ?? option?.value ?? '' ),
	} ) );
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
		placeholder: field.placeholder || '',
		autoComplete: type === 'password' ? 'new-password' : undefined,
		disabled,
		onChange,
	} );
}

function ShortcodeBlock() {
	return createElement(
		Card,
		null,
		createElement(
			CardHeader,
			null,
			createElement(
				'strong',
				null,
				__( 'Shortcode', 'anti-spam-for-wordpress' )
			)
		),
		createElement(
			CardBody,
			null,
			createElement(
				'p',
				null,
				__(
					'Use [anti_spam_widget] in custom form templates when automatic placement is not available. Your form handler must verify the submitted proof on the server before processing the submission.',
					'anti-spam-for-wordpress'
				)
			),
			createElement(
				'code',
				{ className: 'asfw-admin-ui-code-block' },
				'[anti_spam_widget mode="captcha" context="custom:contact" name="asfw" layout="extended" appearance="light"]'
			),
			createElement(
				'p',
				{ className: 'asfw-admin-ui-muted' },
				__(
					'Supported attributes: mode, context, name, language, layout (compact or extended), and appearance (light, bright, or dark). Bright is an alias for light. Omit layout or appearance to inherit the site settings. If Custom HTML is disabled, pass mode="captcha" or mode="shortcode" explicitly.',
					'anti-spam-for-wordpress'
				)
			)
		)
	);
}

function findFieldByOption( sections, optionName ) {
	for ( const section of sections ) {
		const fields = Array.isArray( section?.fields ) ? section.fields : [];
		const field = fields.find( ( item ) => item?.option === optionName );
		if ( field ) {
			return field;
		}
	}

	return null;
}

function removeFieldByOption( sections, optionName ) {
	return sections.map( ( section ) => ( {
		...section,
		fields: ( Array.isArray( section?.fields )
			? section.fields
			: []
		).filter( ( field ) => field?.option !== optionName ),
	} ) );
}

function PrivacyPolicyTextCard( {
	payload,
	legalBasisField,
	legalBasisValue,
	values,
	onChange,
	isSaving,
} ) {
	const [ copied, setCopied ] = useState( false );
	const text = String( payload?.text || '' );
	const hasGeneratedText = text !== '';

	const copyText = async () => {
		if ( window.navigator?.clipboard?.writeText ) {
			await window.navigator.clipboard.writeText( text );
			setCopied( true );
			window.setTimeout( () => setCopied( false ), 2000 );
		}
	};

	return createElement(
		Card,
		null,
		createElement(
			CardHeader,
			null,
			createElement(
				'strong',
				null,
				__( 'Privacy policy text', 'anti-spam-for-wordpress' )
			)
		),
		createElement(
			CardBody,
			null,
			legalBasisField
				? createElement( SettingsField, {
						field: legalBasisField,
						value: legalBasisValue,
						values,
						onChange,
				  } )
				: null,
			createElement(
				'p',
				{ className: 'asfw-privacy-policy-note' },
				__(
					'Suggested copy for your privacy policy. This is not legal consultation; consult your lawyer before using it because each site can have different legal requirements.',
					'anti-spam-for-wordpress'
				)
			),
			hasGeneratedText && payload?.summary
				? createElement(
						'p',
						{ className: 'asfw-admin-ui-muted' },
						String( payload.summary )
				  )
				: null,
			! hasGeneratedText
				? createElement(
						'p',
						{ className: 'asfw-admin-ui-muted' },
						__(
							'Use the privacy text legal basis setting above and save your settings so the suggested privacy policy text can be generated.',
							'anti-spam-for-wordpress'
						)
				  )
				: null,
			! hasGeneratedText
				? createElement(
						Flex,
						{ justify: 'flex-start', gap: 3 },
						createElement(
							Button,
							{
								variant: 'primary',
								type: 'submit',
								isBusy: isSaving,
								disabled: isSaving,
							},
							__( 'Save Settings', 'anti-spam-for-wordpress' )
						)
				  )
				: null,
			hasGeneratedText
				? createElement( TextareaControl, {
						label: __(
							'Suggested text',
							'anti-spam-for-wordpress'
						),
						value: text,
						readOnly: true,
						rows: 14,
						className: 'asfw-privacy-policy-textarea',
						onChange: () => {},
				  } )
				: null,
			hasGeneratedText
				? createElement(
						Flex,
						{ justify: 'flex-start', gap: 3, align: 'center' },
						createElement(
							Button,
							{ variant: 'secondary', onClick: copyText },
							copied
								? __( 'Copied', 'anti-spam-for-wordpress' )
								: __( 'Copy text', 'anti-spam-for-wordpress' )
						),
						createElement(
							'span',
							{ className: 'asfw-admin-ui-muted' },
							__(
								'This suggested text updates when you change relevant plugin settings. Review it before updating your privacy policy page.',
								'anti-spam-for-wordpress'
							)
						)
				  )
				: null
		)
	);
}

export default function SettingsTab( {
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
	const killSwitch = payload?.summary?.kill_switch === 'active';
	const privacyLegalBasisField = findFieldByOption(
		sections,
		PRIVACY_LEGAL_BASIS_OPTION
	);
	const editableSections = removeFieldByOption(
		sections,
		PRIVACY_LEGAL_BASIS_OPTION
	);

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
			createElement( PrivacyPolicyTextCard, {
				payload: payload?.privacy_policy_text,
				legalBasisField: privacyLegalBasisField,
				legalBasisValue: values[ PRIVACY_LEGAL_BASIS_OPTION ],
				values,
				onChange: ( next ) =>
					onChange( PRIVACY_LEGAL_BASIS_OPTION, next ),
				isSaving,
			} ),
			editableSections.map( ( section ) =>
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
			createElement( ShortcodeBlock ),
			createElement(
				Flex,
				{ justify: 'flex-start', gap: 3 },
				createElement(
					Button,
					{
						variant: 'primary',
						type: 'submit',
						isBusy: isSaving,
						disabled: isSaving,
					},
					__( 'Save Settings', 'anti-spam-for-wordpress' )
				)
			)
		)
	);
}
