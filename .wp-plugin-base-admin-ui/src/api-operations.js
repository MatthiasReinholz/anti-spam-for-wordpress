import { fetchOperation, getOperationPath } from '../shared/api-client';

export function buildQuery( params = {} ) {
	const search = new URLSearchParams();
	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value !== undefined && value !== null && value !== '' ) {
			search.set( key, String( value ) );
		}
	} );
	return search.toString();
}

export function readOperation( operation, query, signal ) {
	const path = getOperationPath( operation );
	return fetchOperation( operation, {
		path: query ? `${ path }?${ query }` : path,
		signal,
	} );
}
