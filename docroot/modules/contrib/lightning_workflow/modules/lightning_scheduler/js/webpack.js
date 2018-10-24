const env_options = {
    modules: false,
    targets: {
        browsers: ['last 2 versions']
    }
};

module.exports = {

    entry: './index.js',

    output: {
        filename: 'index.js',
        path: require('path').resolve(__dirname, 'dist')
    },

    externals: {
        react: 'React',
        'react-dom': 'ReactDOM'
    },

    module: {
        loaders: [
            {
                test: /.js$/,
                loader: 'babel-loader',
                exclude: ['/node_modules/'],
                options: {
                    presets: ['react', ['env', env_options]]
                }
            }
        ]
    }

};
