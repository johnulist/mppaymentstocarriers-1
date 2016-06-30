module.exports = function(grunt) {

    grunt.initConfig({
        compress: {
            main: {
                options: {
                    archive: 'mppaymentstocarriers.zip'
                },
                files: [
                    {src: ['controllers/**'], dest: 'mppaymentstocarriers/', filter: 'isFile'},
                    {src: ['classes/**'], dest: 'mppaymentstocarriers/', filter: 'isFile'},
                    {src: ['docs/**'], dest: 'mppaymentstocarriers/', filter: 'isFile'},
                    {src: ['override/**'], dest: 'mppaymentstocarriers/', filter: 'isFile'},
                    {src: ['logs/**'], dest: 'mppaymentstocarriers/', filter: 'isFile'},
                    {src: ['vendor/**'], dest: 'mppaymentstocarriers/', filter: 'isFile'},
                    {src: ['translations/**'], dest: 'mppaymentstocarriers/', filter: 'isFile'},
                    {src: ['upgrade/**'], dest: 'mppaymentstocarriers/', filter: 'isFile'},
                    {src: ['optionaloverride/**'], dest: 'mppaymentstocarriers/', filter: 'isFile'},
                    {src: ['oldoverride/**'], dest: 'mppaymentstocarriers/', filter: 'isFile'},
                    {src: ['sql/**'], dest: 'mppaymentstocarriers/', filter: 'isFile'},
                    {src: ['lib/**'], dest: 'mppaymentstocarriers/', filter: 'isFile'},
                    {src: ['defaultoverride/**'], dest: 'mppaymentstocarriers/', filter: 'isFile'},
                    {src: ['views/**'], dest: 'mppaymentstocarriers/', filter: 'isFile'},
                    {src: 'config.xml', dest: 'mppaymentstocarriers/'},
                    {src: 'index.php', dest: 'mppaymentstocarriers/'},
                    {src: 'mppaymentstocarriers.php', dest: 'mppaymentstocarriers/'},
                    {src: 'logo.png', dest: 'mppaymentstocarriers/'},
                    {src: 'logo.gif', dest: 'mppaymentstocarriers/'},
                    {src: 'LICENSE', dest: 'mppaymentstocarriers/'}
                ]
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-compress');

    grunt.registerTask('default', ['compress']);
};