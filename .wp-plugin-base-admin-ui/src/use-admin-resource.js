import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { readOperation } from './api-operations';

/**
 * Cache completed reads; only a changed query or explicit retry starts another.
 * @param {string}  operation Registered operation identifier.
 * @param {string}  query     Encoded query string.
 * @param {boolean} enabled   Whether this view is active.
 */
export default function useAdminResource( operation, query, enabled ) {
	const [ resource, setResource ] = useState( {
		status: 'idle',
		data: null,
		error: null,
	} );
	const [ revision, setRevision ] = useState( 0 );
	const request = useRef( {
		generation: 0,
		key: null,
		revision: -1,
		status: 'idle',
	} );
	const reload = useCallback(
		() => setRevision( ( value ) => value + 1 ),
		[]
	);

	useEffect( () => {
		if ( ! enabled ) {
			return undefined;
		}
		const key = `${ operation }?${ query }`;
		const previous = request.current;
		if (
			previous.key === key &&
			previous.revision === revision &&
			[ 'success', 'error' ].includes( previous.status )
		) {
			return undefined;
		}
		const controller = new AbortController();
		const current = {
			generation: previous.generation + 1,
			key,
			revision,
			status: 'loading',
		};
		request.current = current;
		setResource( ( value ) => ( {
			...value,
			status: 'loading',
			error: null,
		} ) );
		let timedOut = false;
		let timeout;
		// Transport middleware may ignore abort, so the deadline must settle itself.
		Promise.race( [
			Promise.resolve().then( () =>
				readOperation( operation, query, controller.signal )
			),
			new Promise( ( resolve, reject ) => {
				timeout = window.setTimeout( () => {
					timedOut = true;
					reject(
						new Error(
							__(
								'The request timed out. Please try again.',
								'anti-spam-for-wordpress'
							)
						)
					);
					controller.abort();
				}, 30000 );
			} ),
		] )
			.then( ( data ) => {
				if (
					request.current !== current ||
					controller.signal.aborted
				) {
					return;
				}
				current.status = 'success';
				setResource( { status: 'success', data, error: null } );
			} )
			.catch( ( error ) => {
				if (
					request.current !== current ||
					( controller.signal.aborted && ! timedOut )
				) {
					return;
				}
				current.status = 'error';
				setResource( ( value ) => ( {
					...value,
					status: 'error',
					error,
				} ) );
			} )
			.finally( () => window.clearTimeout( timeout ) );
		return () => {
			window.clearTimeout( timeout );
			controller.abort();
			if ( current.status === 'loading' ) {
				current.status = 'idle';
			}
		};
	}, [ operation, query, enabled, revision ] );

	return {
		...resource,
		reload,
		isLoading: resource.status === 'idle' || resource.status === 'loading',
	};
}
