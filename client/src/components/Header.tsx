import { Rocket } from 'lucide-react';
import React from 'react';

const Header: React.FC = () => {
    return (
        <div className="relative overflow-hidden bg-indigo-600">
            {/* Simple solid background with subtle pattern */}
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(255,255,255,0.1),transparent_50%)]"></div>
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_70%_80%,rgba(255,255,255,0.05),transparent_50%)]"></div>

            {/* Animated background elements */}
            <div className="absolute top-0 left-0 h-full w-full">
                <div className="animate-pulse-slow absolute top-10 left-10 h-20 w-20 rounded-full bg-white/10"></div>
                <div
                    className="animate-pulse-slow absolute top-20 right-20 h-16 w-16 rounded-full bg-white/5"
                    style={{ animationDelay: '1s' }}></div>
                <div
                    className="animate-pulse-slow absolute bottom-20 left-1/4 h-12 w-12 rounded-full bg-white/8"
                    style={{ animationDelay: '2s' }}></div>
            </div>

            <div className="relative z-10 py-12 text-white">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-col gap-6 text-center md:flex-row md:items-center md:justify-between md:text-left">
                        <div>
                            <div className="mb-3 flex items-center justify-center gap-3 md:justify-start">
                                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                                    <Rocket className="h-6 w-6 text-white" />
                                </div>
                                <div>
                                    <h1 className="bg-gradient-to-r from-white to-white/80 bg-clip-text text-4xl font-bold text-transparent md:text-5xl">
                                        Task Queue Dashboard
                                    </h1>
                                </div>
                            </div>
                            <p className="text-lg font-medium text-white/90 md:text-xl">
                                Real-time monitoring and management
                            </p>
                            <div className="mt-4 flex items-center justify-center gap-4 md:justify-start">
                                <div className="flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 backdrop-blur-sm">
                                    <div className="h-2 w-2 animate-pulse rounded-full bg-green-400"></div>
                                    <span className="text-sm font-medium">Live</span>
                                </div>
                                <div className="text-sm text-white/70">Auto-refresh every 5s</div>
                            </div>
                        </div>

                        <div style={{ animationDelay: '0.2s' }}>
                            <div className="rounded-xl border border-white/20 bg-white/10 p-6 shadow-lg backdrop-blur-sm">
                                <div className="space-y-2 text-sm text-white/90">
                                    <div className="flex items-center gap-2">
                                        <div className="h-2 w-2 rounded-full bg-white/60"></div>
                                        <span>Built with React & TypeScript</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="h-2 w-2 rounded-full bg-white/60"></div>
                                        <span>Powered by PHP API</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="h-2 w-2 rounded-full bg-white/60"></div>
                                        <span>Real-time Updates</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Header;

