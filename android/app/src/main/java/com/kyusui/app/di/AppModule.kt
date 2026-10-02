package com.kyusui.app.di

import android.content.ContentResolver
import com.kyusui.app.data.repository.AuthRepositoryImpl
import com.kyusui.app.data.repository.CustomerRepositoryImpl
import com.kyusui.app.data.upload.ContentResolverProofFileSource
import com.kyusui.app.data.upload.ProofFileSource
import com.kyusui.app.domain.repository.AuthRepository
import com.kyusui.app.domain.repository.CustomerRepository
import dagger.Binds
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
abstract class AppModule {

    @Binds
    @Singleton
    abstract fun bindAuthRepository(impl: AuthRepositoryImpl): AuthRepository

    @Binds
    @Singleton
    abstract fun bindCustomerRepository(impl: CustomerRepositoryImpl): CustomerRepository

    @Binds
    @Singleton
    abstract fun bindProofFileSource(impl: ContentResolverProofFileSource): ProofFileSource
}

/**
 * `ContentResolver` disuplai sebagai dependency agar `ProofFileSource` tetap
 * testable di JVM unit test tanpa instrumentation.
 */
@Module
@InstallIn(SingletonComponent::class)
object ContentModule {

    @Provides
    @Singleton
    fun provideContentResolver(
        @ApplicationContext context: android.content.Context
    ): ContentResolver = context.contentResolver
}