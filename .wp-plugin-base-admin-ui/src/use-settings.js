import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { fetchOperation } from '../shared/api-client';
import useAdminResource from './use-admin-resource';
import { buildSettingsDraft, mergeSavedDraft } from './settings-model';

export default function useSettings( enabled, onNotice ) {
	const resource = useAdminResource( 'settings.read', '', enabled );
	const [ payload, setPayload ] = useState( null );
	const [ values, setValues ] = useState( {} );
	const [ isSaving, setSaving ] = useState( false );
	const draft = useRef( {} );
	const saving = useRef( false );
	const mounted = useRef( false );

	useEffect( () => {
		mounted.current = true;
		return () => {
			mounted.current = false;
		};
	}, [] );

	useEffect( () => {
		if ( ! resource.data ) {
			return;
		}
		draft.current = buildSettingsDraft( resource.data );
		setValues( draft.current );
		setPayload( resource.data );
	}, [ resource.data ] );

	const onChange = useCallback( ( option, value ) => {
		draft.current = { ...draft.current, [ option ]: value };
		setValues( draft.current );
	}, [] );

	const onSave = useCallback( async () => {
		if ( saving.current || ! payload ) {
			return;
		}
		saving.current = true;
		setSaving( true );
		const submitted = { ...draft.current };
		const controller = new AbortController();
		let timeout;
		try {
			// A timed-out mutation may still commit. Race the timeout explicitly
			// so a transport that ignores abort cannot block saving indefinitely
			// or replace a later retry's result with an old response.
			const response = await Promise.race( [
				fetchOperation( 'settings.update', {
					method: 'POST',
					data: { values: submitted },
					signal: controller.signal,
				} ),
				new Promise( ( resolve, reject ) => {
					timeout = window.setTimeout( () => {
						reject(
							new Error(
								__(
									'The save request timed out. Your edits are retained, but some settings may already have been saved. Retry saving or reload to confirm the stored values.',
									'anti-spam-for-wordpress'
								)
							)
						);
						controller.abort();
					}, 30000 );
				} ),
			] );
			if ( ! mounted.current ) {
				return;
			}
			const nextPayload = response?.settings;
			if ( ! nextPayload || ! Array.isArray( nextPayload.sections ) ) {
				throw new Error(
					__(
						'The settings response was invalid. Reload to confirm the saved values.',
						'anti-spam-for-wordpress'
					)
				);
			}
			draft.current = mergeSavedDraft(
				draft.current,
				submitted,
				buildSettingsDraft( nextPayload )
			);
			setValues( draft.current );
			setPayload( nextPayload );
			onNotice( {
				status: 'success',
				message: response?.privacy_policy_text_updated
					? __(
							'Settings saved. The suggested privacy policy text was updated; review whether your privacy policy page needs changes.',
							'anti-spam-for-wordpress'
					  )
					: __( 'Settings saved.', 'anti-spam-for-wordpress' ),
			} );
		} catch ( error ) {
			if ( mounted.current ) {
				onNotice( {
					status: 'error',
					message:
						error?.message ||
						__(
							'Failed to save settings.',
							'anti-spam-for-wordpress'
						),
				} );
			}
		} finally {
			window.clearTimeout( timeout );
			saving.current = false;
			if ( mounted.current ) {
				setSaving( false );
			}
		}
	}, [ payload, onNotice ] );

	return { ...resource, payload, values, isSaving, onChange, onSave };
}
