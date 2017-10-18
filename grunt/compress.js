module.exports = {
	/**
	 * grunt-contrib-compress
	 *
	 * Compress files and folders.
	 *
	 * Used in grunt package to create production-ready zip file.
	 *
	 * @link https://www.npmjs.com/package/grunt-contrib-compress
	 */
	main: {
		options: {
			mode: 'zip',
			archive: './dist/<%=package.name %>.zip'
		},
		expand: true,
		cwd: 'dist/<%= package.name %>/',
		src: ['**/*'],
		dest: '<%= package.name %>/'
	}
};
