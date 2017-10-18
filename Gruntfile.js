/*global module:false*/
module.exports = function ( grunt ) {
	'use strict';

	/**
	 * load-grunt-config
	 *
	 * Grunt plugin that lets you break up your Gruntfile config by task
	 *
	 * @link https://www.npmjs.com/package/load-grunt-config
	 */
	grunt.configs = require( 'load-grunt-config' )( grunt );

	/**
	 * load-grunt-tasks
	 *
	 * Load multiple grunt tasks using globbing patterns
	 *
	 * This module will read the dependencies/devDependencies/peerDependencies
	 * in your package.json and load grunt tasks that match the provided patterns.
	 *
	 * @link https://www.npmjs.com/package/load-grunt-tasks
	 */
	require( 'load-grunt-tasks' )( grunt );

	/**
	 * time-grunt
	 *
	 * Display the elapsed execution time of grunt tasks
	 *
	 * @link https://www.npmjs.com/package/time-grunt
	 */
	require( 'time-grunt' )( grunt );
};
