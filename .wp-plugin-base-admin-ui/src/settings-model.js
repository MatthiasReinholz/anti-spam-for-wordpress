export function buildSettingsDraft( payload ) {
	const values = {};
	for ( const section of Array.isArray( payload?.sections )
		? payload.sections
		: [] ) {
		for ( const field of Array.isArray( section?.fields )
			? section.fields
			: [] ) {
			if ( field?.option ) {
				values[ field.option ] = field.value;
			}
		}
	}
	return values;
}

/**
 * Apply server normalization only to fields unchanged since this save began.
 * @param {Object} current    Current editable values.
 * @param {Object} submitted  Values captured when the save began.
 * @param {Object} normalized Values returned by the server.
 */
export function mergeSavedDraft( current, submitted, normalized ) {
	const merged = { ...current };
	for ( const [ option, value ] of Object.entries( normalized ) ) {
		if (
			JSON.stringify( current[ option ] ) ===
			JSON.stringify( submitted[ option ] )
		) {
			merged[ option ] = value;
		}
	}
	return merged;
}
